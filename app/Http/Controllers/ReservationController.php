<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventSeat;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationCooldown;
use App\Models\User;
use App\Services\StripePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class ReservationController extends Controller
{
    public function store(
        Request $request,
        Event $event,
        StripePaymentService $stripePaymentService,
    ): RedirectResponse|SymfonyResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'seats' => ['required', 'array', 'min:1'],
            'seats.*' => [
                'required',
                'string',
                'regex:/^[1-9][0-9]*-[1-9][0-9]*$/',
                'distinct',
            ],
        ]);

        $reservation = DB::transaction(function () use (
            $event,
            $user,
            $validated,
        ): Reservation {
            $event = Event::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $event->status !== 'published'
                || Carbon::parse($event->starts_at)->isPast()
                || Carbon::parse($event->starts_at)->equalTo(now())
            ) {
                throw ValidationException::withMessages([
                    'seats' => 'Ovaj događaj više nije dostupan za rezervacije.',
                ]);
            }

            if ($event->registrations()
                ->where('user_id', $user->id)
                ->where('status', 'registered')
                ->exists()) {
                throw ValidationException::withMessages([
                    'seats' => 'Već imate aktivnu prijavu za ovaj događaj.',
                ]);
            }

            $pendingReservation = Reservation::query()
                ->where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->where('status', Reservation::STATUS_PENDING)
                ->where('expires_at', '>', now())
                ->exists();

            if ($pendingReservation) {
                throw ValidationException::withMessages([
                    'seats' => 'Već imate rezervaciju na čekanju za ovaj događaj. Završite ili otkažite postojeće plaćanje.',
                ]);
            }

            $cooldown = ReservationCooldown::query()
                ->where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first();

            if ($cooldown && $cooldown->expires_at->isFuture()) {
                throw ValidationException::withMessages([
                    'seats' => 'Morate sačekati prije nove rezervacije za ovaj događaj.',
                ]);
            }

            if ($cooldown) {
                $cooldown->delete();
            }

            if (count($validated['seats']) > $event->max_tickets_per_user) {
                throw ValidationException::withMessages([
                    'seats' => 'Premašili ste maksimalan broj karata za ovaj događaj.',
                ]);
            }

            $event->load('seatPriceGroups');

            // Inicijalizacija inventara sjedišta.
            $seatRows = [];

            for ($row = 1; $row <= $event->seat_rows; $row++) {
                for ($seat = 1; $seat <= $event->seat_columns; $seat++) {
                    $seatRows[] = [
                        'event_id' => $event->id,
                        'row_number' => $row,
                        'seat_number' => $seat,
                        'status' => EventSeat::STATUS_AVAILABLE,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            foreach (array_chunk($seatRows, 500) as $chunk) {
                DB::table('event_seats')->insertOrIgnore($chunk);
            }

            /** @var list<array{row: int, number: int}> $positions */
            $positions = array_map(
                static function (string $seat): array {
                    [$row, $number] = array_map(
                        'intval',
                        explode('-', $seat),
                    );

                    return [
                        'row' => $row,
                        'number' => $number,
                    ];
                },
                $validated['seats'],
            );

            foreach ($positions as $position) {
                if (
                    $position['row'] > $event->seat_rows
                    || $position['number'] > $event->seat_columns
                ) {
                    throw ValidationException::withMessages([
                        'seats' => 'Odabrano sjedište ne postoji.',
                    ]);
                }
            }

            $selectedSeats = EventSeat::query()
                ->where('event_id', $event->id)
                ->where(function ($query) use ($positions) {
                    foreach ($positions as $position) {
                        $query->orWhere(function ($query) use ($position) {
                            $query->where('row_number', $position['row'])
                                ->where('seat_number', $position['number']);
                        });
                    }
                })
                ->orderBy('row_number')
                ->orderBy('seat_number')
                ->lockForUpdate()
                ->get();

            if (
                $selectedSeats->count() !== count($validated['seats'])
                || $selectedSeats->contains(
                    fn (EventSeat $seat): bool => $seat->status !== EventSeat::STATUS_AVAILABLE
                )
            ) {
                throw ValidationException::withMessages([
                    'seats' => 'Jedno ili više odabranih sjedišta više nije dostupno.',
                ]);
            }

            // Cijene se računaju isključivo na backendu.
            $totalCents = 0;
            $seatPrices = [];

            foreach ($selectedSeats as $seat) {
                $priceGroup = $event->seatPriceGroups->first(
                    fn ($group): bool => $seat->row_number >= $group->from_row
                        && $seat->row_number <= $group->to_row
                );

                if (! $priceGroup) {
                    throw ValidationException::withMessages([
                        'seats' => 'Nije moguće odrediti cijenu odabranog sjedišta.',
                    ]);
                }

                $priceCents = (int) round(
                    (float) $priceGroup->price * 100,
                );

                $seatPrices[$seat->id] = $priceCents;
                $totalCents += $priceCents;
            }

            $totalAmount = number_format(
                $totalCents / 100,
                2,
                '.',
                '',
            );

            $reservation = Reservation::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'status' => Reservation::STATUS_PENDING,
                'total_amount' => $totalAmount,
                'currency' => 'bam',
                'expires_at' => now()->addMinutes(32),
            ]);

            foreach ($selectedSeats as $seat) {
                $reservation->reservationSeats()->create([
                    'event_seat_id' => $seat->id,
                    'price' => number_format(
                        $seatPrices[$seat->id] / 100,
                        2,
                        '.',
                        '',
                    ),
                ]);

                $seat->update([
                    'status' => EventSeat::STATUS_RESERVED,
                    'reservation_id' => $reservation->id,
                ]);
            }

            // Besplatna rezervacija se odmah potvrđuje.
            if ($totalCents === 0) {
                $reservation->update([
                    'status' => Reservation::STATUS_CONFIRMED,
                    'confirmed_at' => now(),
                ]);

                EventRegistration::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'event_id' => $event->id,
                    ],
                    [
                        'status' => 'registered',
                        'attended' => false,
                    ],
                );

                $selectedSeats->each(fn (EventSeat $seat) => $seat->update([
                    'status' => EventSeat::STATUS_SOLD,
                ]));

                return $reservation;
            }

            Payment::create([
                'reservation_id' => $reservation->id,
                'provider' => 'stripe',
                'amount' => $totalAmount,
                'currency' => 'bam',
                'status' => Payment::STATUS_PENDING,
            ]);

            return $reservation;
        }, 3);

        if ($reservation->status === Reservation::STATUS_CONFIRMED) {
            return redirect()
                ->route('events.show', $event->slug)
                ->with('success', 'Vaša besplatna rezervacija je potvrđena.');
        }

        $payment = $reservation->payments()->latest()->firstOrFail();

        try {
            $session = $stripePaymentService->createCheckoutSession(
                $reservation,
                $payment,
            );

            if (! $session->url) {
                throw new \RuntimeException(
                    'Stripe nije vratio adresu za plaćanje.',
                );
            }

            return Inertia::location($session->url);
        } catch (Throwable $exception) {
            DB::transaction(function () use (
                $reservation,
            ): void {
                // Isti redoslijed zaključavanja kao u ostalim procesima.
                Event::query()
                    ->whereKey($reservation->event_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedReservation = Reservation::query()
                    ->whereKey($reservation->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $lockedReservation
                    || $lockedReservation->status !== Reservation::STATUS_PENDING
                ) {
                    return;
                }

                $lockedPayment = Payment::query()
                    ->where('reservation_id', $lockedReservation->id)
                    ->lockForUpdate()
                    ->first();

                // Ne oslobađaj sjedišta ako je uplata već potvrđena.
                if (
                    $lockedPayment
                    && $lockedPayment->status === Payment::STATUS_PAID
                ) {
                    return;
                }

                EventSeat::query()
                    ->where('reservation_id', $lockedReservation->id)
                    ->where('status', EventSeat::STATUS_RESERVED)
                    ->update([
                        'status' => EventSeat::STATUS_AVAILABLE,
                        'reservation_id' => null,
                    ]);

                $lockedReservation->update([
                    'status' => Reservation::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);

                if (
                    $lockedPayment
                    && $lockedPayment->status === Payment::STATUS_PENDING
                ) {
                    $lockedPayment->update([
                        'status' => Payment::STATUS_FAILED,
                    ]);
                }
            }, 3);

            report($exception);

            return back()->withErrors([
                'seats' => 'Plaćanje trenutno nije moguće pokrenuti. Pokušajte ponovo.',
            ]);
        }
    }

    /**
     * Povratak sa Stripe Checkout stranice.
     * Ova metoda ne potvrđuje uplatu. To radi isključivo webhook.
     */
    public function success(
        Request $request,
        Reservation $reservation,
    ): Response {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $reservation->user_id === $user->id,
            403,
        );

        $reservation->load('event');

        return Inertia::render('reservations/success', [
            'reservation' => [
                'id' => $reservation->id,
                'status' => $reservation->status,
                'total_amount' => $reservation->total_amount,
                'currency' => strtoupper($reservation->currency),
                'event_title' => $reservation->event->title,
            ],
        ]);
    }

    /**
     * Otkazivanje Stripe Checkout procesa.
     * Sjedišta se oslobađaju samo ako Stripe sesija nije više aktivna.
     */
    public function cancel(
        Request $request,
        Reservation $reservation,
        StripePaymentService $stripePaymentService,
    ): Response {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $reservation->user_id === $user->id,
            403,
        );

        // Dohvati uplatu prije poziva Stripe API-ja.
        $payment = $reservation->payments()->latest()->first();

        if ($reservation->status !== Reservation::STATUS_PENDING) {
            return Inertia::render('reservations/cancel', [
                'cancelled' => false,
                'message' => 'Ova rezervacija više nije na čekanju.',
            ]);
        }

        // Stripe API poziv mora biti izvan DB transakcije.
        if (
            $payment
            && ! $stripePaymentService->expireCheckoutSession($payment)
        ) {
            return Inertia::render('reservations/cancel', [
                'cancelled' => false,
                'message' => 'Plaćanje još nije moguće sigurno otkazati. Provjerite status rezervacije.',
            ]);
        }

        $cancelled = DB::transaction(function () use (
            $reservation,
        ): bool {
            // Redoslijed zaključavanja mora biti isti kao u webhooku
            // i komandi za istek: Event -> Reservation -> Payment -> Seats.
            Event::query()
                ->whereKey($reservation->event_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedReservation = Reservation::query()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->first();

            if (
                ! $lockedReservation
                || $lockedReservation->status !== Reservation::STATUS_PENDING
            ) {
                return false;
            }

            $lockedPayment = Payment::query()
                ->where('reservation_id', $lockedReservation->id)
                ->lockForUpdate()
                ->first();

            // Zaštita ako je uplata potvrđena u međuvremenu.
            if (
                $lockedPayment
                && $lockedPayment->status === Payment::STATUS_PAID
            ) {
                return false;
            }

            EventSeat::query()
                ->where('reservation_id', $lockedReservation->id)
                ->where('status', EventSeat::STATUS_RESERVED)
                ->update([
                    'status' => EventSeat::STATUS_AVAILABLE,
                    'reservation_id' => null,
                ]);

            $lockedReservation->update([
                'status' => Reservation::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            if (
                $lockedPayment
                && $lockedPayment->status === Payment::STATUS_PENDING
            ) {
                $lockedPayment->update([
                    'status' => Payment::STATUS_FAILED,
                ]);
            }

            return true;
        }, 3);

        return Inertia::render('reservations/cancel', [
            'cancelled' => $cancelled,
            'message' => $cancelled
                ? 'Rezervacija je otkazana, a sjedišta su ponovo dostupna.'
                : 'Rezervacija je već obrađena i nije je moguće otkazati.',
        ]);
    }
}
