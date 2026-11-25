<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventSeat;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationCooldown;
use App\Models\ReservationSeat;
use App\Models\StripeWebhookEvent;
use App\Services\StripePaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Refund;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripePaymentService $stripePaymentService,
    ): JsonResponse {
        $secret = config('services.stripe.webhook_secret');
        $signature = $request->header('Stripe-Signature');

        if (
            ! is_string($secret)
            || $secret === ''
            || ! is_string($signature)
            || $signature === ''
        ) {
            return response()->json([
                'message' => 'Neispravna konfiguracija webhooka.',
            ], 400);
        }

        try {
            $stripeEvent = Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $secret,
            );
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Neispravan Stripe webhook zahtjev.',
            ], 400);
        }

        try {
            try {
                $webhook = StripeWebhookEvent::query()->firstOrCreate(
                    ['stripe_event_id' => $stripeEvent->id],
                    [
                        'event_type' => $stripeEvent->type,
                        'status' => StripeWebhookEvent::STATUS_RECEIVED,
                    ],
                );
            } catch (QueryException $exception) {
                $webhook = StripeWebhookEvent::query()
                    ->where('stripe_event_id', $stripeEvent->id)
                    ->first();

                if (! $webhook) {
                    throw $exception;
                }
            }

            if ($webhook->status === StripeWebhookEvent::STATUS_PROCESSED) {
                return response()->json(['received' => true]);
            }

            $refundPaymentId = DB::transaction(function () use (
                $stripeEvent,
                $webhook,
            ): ?int {
                $lockedWebhook = StripeWebhookEvent::query()
                    ->whereKey($webhook->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedWebhook->status
                    === StripeWebhookEvent::STATUS_PROCESSED
                ) {
                    return null;
                }

                $lockedWebhook->update([
                    'status' => StripeWebhookEvent::STATUS_PROCESSING,
                ]);

                $stripeObject = $stripeEvent->data->object;

                if (
                    $stripeEvent->type === 'refund.updated'
                    && $stripeObject instanceof Refund
                ) {
                    $payment = $this->processRefundUpdated($stripeObject);

                    $lockedWebhook->update([
                        'status' => StripeWebhookEvent::STATUS_PROCESSED,
                        'processed_at' => now(),
                        'payment_id' => $payment->id,
                    ]);

                    return null;
                }

                if (! $stripeObject instanceof Session) {
                    $lockedWebhook->update([
                        'status' => StripeWebhookEvent::STATUS_PROCESSED,
                        'processed_at' => now(),
                    ]);

                    return null;
                }

                $refundPaymentId = null;

                switch ($stripeEvent->type) {
                    case 'checkout.session.completed':
                    case 'checkout.session.async_payment_succeeded':
                        if ($stripeObject->payment_status === 'paid') {
                            $refundPaymentId = $this->processPaidSession(
                                $stripeObject,
                            );
                        }

                        break;

                    case 'checkout.session.expired':
                    case 'checkout.session.async_payment_failed':
                        $this->processExpiredSession($stripeObject);

                        break;
                }

                if ($refundPaymentId === null) {
                    $lockedWebhook->update([
                        'status' => StripeWebhookEvent::STATUS_PROCESSED,
                        'processed_at' => now(),
                    ]);
                } else {
                    $lockedWebhook->update([
                        'payment_id' => $refundPaymentId,
                    ]);
                }

                return $refundPaymentId;
            }, 3);

            if ($refundPaymentId !== null) {
                $payment = Payment::query()->findOrFail($refundPaymentId);

                $refund = $stripePaymentService->refundPayment(
                    $payment,
                    $payment->provider_payment_intent_id,
                );

                DB::transaction(function () use (
                    $payment,
                    $refund,
                    $webhook,
                ): void {
                    $lockedWebhook = StripeWebhookEvent::query()
                        ->whereKey($webhook->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lockedPayment = Payment::query()
                        ->whereKey($payment->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lockedPayment->update([
                        'provider_refund_id' => $refund->id,
                    ]);

                    if ($refund->status === Refund::STATUS_SUCCEEDED) {
                        $lockedPayment->update([
                            'status' => Payment::STATUS_REFUNDED,
                            'refunded_at' => $lockedPayment->refunded_at ?? now(),
                        ]);
                    }

                    // Stripe je prihvatio zahtjev za refund.
                    // Event se završava, a refund.updated prati konačan status.
                    $lockedWebhook->update([
                        'status' => StripeWebhookEvent::STATUS_PROCESSED,
                        'processed_at' => now(),
                        'payment_id' => $payment->id,
                    ]);
                });
            }

            return response()->json(['received' => true]);
        } catch (Throwable $exception) {
            report($exception);

            if (isset($webhook)) {
                StripeWebhookEvent::query()
                    ->whereKey($webhook->id)
                    ->where(
                        'status',
                        '!=',
                        StripeWebhookEvent::STATUS_PROCESSED,
                    )
                    ->update([
                        'status' => StripeWebhookEvent::STATUS_FAILED,
                        'processed_at' => null,
                    ]);
            }

            return response()->json([
                'message' => 'Obrada Stripe događaja nije uspjela.',
            ], 500);
        }
    }

    /**
     * Obrađuje promjenu statusa Stripe povrata novca.
     */
    private function processRefundUpdated(Refund $refund): Payment
    {
        $paymentIntentId = $this->refundPaymentIntentId($refund);

        $paymentQuery = Payment::query()
            ->where('provider', 'stripe');

        if ($refund->id !== '') {
            $paymentQuery->where(function ($query) use (
                $refund,
                $paymentIntentId,
            ): void {
                $query->where('provider_refund_id', $refund->id);

                if ($paymentIntentId !== null) {
                    $query->orWhere(
                        'provider_payment_intent_id',
                        $paymentIntentId,
                    );
                }
            });
        }

        $payment = $paymentQuery
            ->lockForUpdate()
            ->first();

        if (! $payment) {
            throw new \RuntimeException(
                'Nije pronađena uplata za Stripe povrat novca.',
            );
        }

        if (
            $payment->provider_refund_id !== null
            && $payment->provider_refund_id !== $refund->id
        ) {
            throw new \RuntimeException(
                'Stripe povrat novca ne odgovara evidentiranom povratu.',
            );
        }

        if (
            $paymentIntentId !== null
            && $payment->provider_payment_intent_id !== $paymentIntentId
        ) {
            throw new \RuntimeException(
                'Stripe Payment Intent ne odgovara evidentiranoj uplati.',
            );
        }

        $expectedAmount = (int) round(
            (float) $payment->amount * 100,
        );

        if (
            $refund->amount !== $expectedAmount
            || strtolower((string) $refund->currency)
                !== strtolower($payment->currency)
        ) {
            throw new \RuntimeException(
                'Iznos ili valuta Stripe povrata ne odgovaraju uplati.',
            );
        }

        $updates = [
            'provider_refund_id' => $refund->id,
        ];

        if ($refund->status === Refund::STATUS_SUCCEEDED) {
            $updates['status'] = Payment::STATUS_REFUNDED;
            $updates['refunded_at'] = $payment->refunded_at ?? now();
        }

        $payment->update($updates);

        return $payment;
    }

    /**
     * Vraća ID Payment Intenta iz Stripe Refund objekta.
     */
    private function refundPaymentIntentId(Refund $refund): ?string
    {
        $paymentIntent = $refund->payment_intent ?? null;

        if (is_string($paymentIntent) && $paymentIntent !== '') {
            return $paymentIntent;
        }

        if (is_object($paymentIntent) && isset($paymentIntent->id)) {
            return (string) $paymentIntent->id;
        }

        return null;
    }

    /**
     * Obrađuje uspješnu uplatu i vraća ID plaćanja ako je potreban refund.
     */
    private function processPaidSession(Session $session): ?int
    {
        $reservationId = $session->metadata->reservation_id ?? null;
        $paymentId = $session->metadata->payment_id ?? null;

        if (
            ! is_numeric($reservationId)
            || ! is_numeric($paymentId)
        ) {
            throw new \RuntimeException(
                'Stripe sesija nema ispravne identifikatore.',
            );
        }

        $reservationReference = Reservation::query()
            ->select(['id', 'event_id'])
            ->whereKey((int) $reservationId)
            ->first();

        if (! $reservationReference) {
            throw new \RuntimeException(
                'Rezervacija iz Stripe sesije ne postoji.',
            );
        }

        $event = Event::query()
            ->whereKey($reservationReference->event_id)
            ->lockForUpdate()
            ->firstOrFail();

        $reservation = Reservation::query()
            ->whereKey($reservationReference->id)
            ->lockForUpdate()
            ->firstOrFail();

        $payment = Payment::query()
            ->whereKey((int) $paymentId)
            ->where('reservation_id', $reservation->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $payment->provider !== 'stripe'
            || $payment->provider_session_id !== $session->id
        ) {
            throw new \RuntimeException(
                'Stripe sesija ne odgovara evidentiranom plaćanju.',
            );
        }

        $expectedAmount = (int) round(
            (float) $payment->amount * 100,
        );

        if (
            $session->amount_total !== $expectedAmount
            || strtolower((string) $session->currency)
                !== strtolower($payment->currency)
        ) {
            throw new \RuntimeException(
                'Iznos ili valuta Stripe uplate ne odgovaraju rezervaciji.',
            );
        }

        $paymentIntentId = $this->paymentIntentId($session);

        if (! $paymentIntentId) {
            throw new \RuntimeException(
                'Stripe uplata nema Payment Intent.',
            );
        }

        if (
            $reservation->status === Reservation::STATUS_CONFIRMED
            && $payment->status === Payment::STATUS_PAID
        ) {
            return null;
        }

        $payment->update([
            'status' => Payment::STATUS_PAID,
            'provider_payment_intent_id' => $paymentIntentId,
            'paid_at' => $payment->paid_at ?? now(),
        ]);

        if (in_array($reservation->status, [
            Reservation::STATUS_CANCELLED,
            Reservation::STATUS_EXPIRED,
        ], true)) {
            return $payment->id;
        }

        $reservationSeats = ReservationSeat::query()
            ->where('reservation_id', $reservation->id)
            ->get();

        $seatIds = $reservationSeats
            ->pluck('event_seat_id')
            ->all();

        $seats = EventSeat::query()
            ->whereIn('id', $seatIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $allSeatsAvailable = $seats->count() === count($seatIds)
            && $seats->isNotEmpty()
            && $seats->every(
                fn (EventSeat $seat): bool => $seat->status === EventSeat::STATUS_AVAILABLE
                    || (
                        $seat->status === EventSeat::STATUS_RESERVED
                        && $seat->reservation_id === $reservation->id
                    ),
            );

        $eventIsAvailable = $event->status === 'published'
            && $event->starts_at->isFuture();

        if (! $allSeatsAvailable || ! $eventIsAvailable) {
            return $payment->id;
        }

        foreach ($seats as $seat) {
            $seat->update([
                'status' => EventSeat::STATUS_SOLD,
                'reservation_id' => $reservation->id,
            ]);
        }

        $reservation->update([
            'status' => Reservation::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $this->activateRegistration($reservation);

        ReservationCooldown::query()
            ->where('user_id', $reservation->user_id)
            ->where('event_id', $reservation->event_id)
            ->delete();

        return null;
    }

    /**
     * Obrađuje istek ili neuspjeh Stripe Checkout sesije.
     */
    private function processExpiredSession(Session $session): void
    {
        $reservationId = $session->metadata->reservation_id ?? null;
        $paymentId = $session->metadata->payment_id ?? null;

        if (
            ! is_numeric($reservationId)
            || ! is_numeric($paymentId)
        ) {
            return;
        }

        $reservationReference = Reservation::query()
            ->select(['id', 'event_id'])
            ->whereKey((int) $reservationId)
            ->first();

        if (! $reservationReference) {
            return;
        }

        Event::query()
            ->whereKey($reservationReference->event_id)
            ->lockForUpdate()
            ->firstOrFail();

        $reservation = Reservation::query()
            ->whereKey($reservationReference->id)
            ->lockForUpdate()
            ->firstOrFail();

        $payment = Payment::query()
            ->whereKey((int) $paymentId)
            ->where('reservation_id', $reservation->id)
            ->lockForUpdate()
            ->first();

        if (
            ! $payment
            || $payment->provider_session_id !== $session->id
            || $payment->status === Payment::STATUS_PAID
            || $payment->status === Payment::STATUS_REFUNDED
        ) {
            return;
        }

        if ($reservation->status !== Reservation::STATUS_PENDING) {
            return;
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

        $payment->update([
            'status' => Payment::STATUS_FAILED,
        ]);

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
    }

    /**
     * Kreira prijavu ili ponovo aktivira postojeću.
     */
    private function activateRegistration(Reservation $reservation): void
    {
        $registration = EventRegistration::query()
            ->where('user_id', $reservation->user_id)
            ->where('event_id', $reservation->event_id)
            ->lockForUpdate()
            ->first();

        if ($registration) {
            $registration->update([
                'status' => 'registered',
                'attended' => false,
            ]);

            return;
        }

        EventRegistration::create([
            'user_id' => $reservation->user_id,
            'event_id' => $reservation->event_id,
            'status' => 'registered',
            'attended' => false,
        ]);
    }

    /**
     * Vraća ID Stripe Payment Intenta.
     */
    private function paymentIntentId(Session $session): ?string
    {
        $paymentIntent = $session->payment_intent ?? null;

        if (is_string($paymentIntent)) {
            return $paymentIntent;
        }

        if (is_object($paymentIntent) && isset($paymentIntent->id)) {
            return (string) $paymentIntent->id;
        }

        return null;
    }
}
