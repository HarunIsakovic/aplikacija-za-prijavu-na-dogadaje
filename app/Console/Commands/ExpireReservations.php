<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventSeat;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationCooldown;
use App\Services\StripePaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Automatski ističe rezervacije i oslobađa sjedišta';

    public function handle(StripePaymentService $stripePaymentService): int
    {
        $reservations = Reservation::query()
            ->where('status', Reservation::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->get(['id', 'event_id']);

        $expiredCount = 0;

        foreach ($reservations as $reservationReference) {
            try {
                // Učitavamo uplatu bez zaključavanja transakcije.
                $payment = Payment::query()
                    ->where('reservation_id', $reservationReference->id)
                    ->first();

                if ($payment && $payment->status === Payment::STATUS_PAID) {
                    continue;
                }

                // Stripe API poziv izvršavamo izvan DB transakcije.
                $checkoutExpired = ! $payment
                    || $stripePaymentService->expireCheckoutSession($payment);

                if (! $checkoutExpired) {
                    continue;
                }

                $expired = DB::transaction(function () use (
                    $reservationReference,
                ): bool {
                    // Redoslijed zaključavanja: Event -> Reservation -> Payment.
                    Event::query()
                        ->whereKey($reservationReference->event_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $reservation = Reservation::query()
                        ->whereKey($reservationReference->id)
                        ->lockForUpdate()
                        ->first();

                    if (
                        ! $reservation
                        || $reservation->status !== Reservation::STATUS_PENDING
                        || $reservation->expires_at->isFuture()
                    ) {
                        return false;
                    }

                    $payment = $reservation->payments()
                        ->lockForUpdate()
                        ->first();

                    // Zaštita ako je uplata potvrđena tokom obrade.
                    if ($payment && $payment->status === Payment::STATUS_PAID) {
                        return false;
                    }

                    EventSeat::query()
                        ->where('reservation_id', $reservation->id)
                        ->where('status', EventSeat::STATUS_RESERVED)
                        ->update([
                            'status' => EventSeat::STATUS_AVAILABLE,
                            'reservation_id' => null,
                        ]);

                    $reservation->update([
                        'status' => Reservation::STATUS_EXPIRED,
                        'expired_at' => now(),
                    ]);

                    if ($payment && $payment->status === Payment::STATUS_PENDING) {
                        $payment->update([
                            'status' => Payment::STATUS_FAILED,
                        ]);
                    }

                    ReservationCooldown::query()->updateOrCreate(
                        [
                            'user_id' => $reservation->user_id,
                            'event_id' => $reservation->event_id,
                        ],
                        [
                            'reservation_id' => $reservation->id,
                            'expires_at' => now()->addMinutes(15),
                        ],
                    );

                    return true;
                }, 3);

                if ($expired) {
                    $expiredCount++;
                }
            } catch (Throwable $exception) {
                report($exception);

                $this->error(
                    "Greška pri isteku rezervacije ID {$reservationReference->id}.",
                );
            }
        }

        $this->info("Broj isteklih rezervacija: {$expiredCount}");

        return self::SUCCESS;
    }
}
