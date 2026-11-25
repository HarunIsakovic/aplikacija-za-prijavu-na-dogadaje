<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;
use Stripe\StripeClient;

class StripePaymentService
{
    private ?StripeClient $stripe = null;

    private function stripe(): StripeClient
    {
        if ($this->stripe instanceof StripeClient) {
            return $this->stripe;
        }

        $secret = config('services.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            throw new \RuntimeException(
                'Stripe API ključ nije konfigurisan.',
            );
        }

        $this->stripe = new StripeClient($secret);

        return $this->stripe;
    }

    /**
     * Kreira Stripe Checkout sesiju za rezervaciju.
     *
     * @throws ApiErrorException
     */
    public function createCheckoutSession(
        Reservation $reservation,
        Payment $payment,
    ): Session {
        $reservation->loadMissing([
            'event',
            'reservationSeats.eventSeat',
        ]);

        $lineItems = $reservation->reservationSeats
            ->map(function (ReservationSeat $reservationSeat) use ($reservation): array {
                $eventSeat = $reservationSeat->eventSeat;

                return [
                    'price_data' => [
                        'currency' => strtolower($reservation->currency),
                        'product_data' => [
                            'name' => sprintf(
                                'Sjedište %d, red %d',
                                $eventSeat->seat_number,
                                $eventSeat->row_number,
                            ),
                        ],
                        'unit_amount' => (int) round(
                            (float) $reservationSeat->price * 100,
                        ),
                    ],
                    'quantity' => 1,
                ];
            })
            ->values()
            ->all();

        if ($lineItems === []) {
            throw new \RuntimeException(
                'Rezervacija nema nijedno sjedište.',
            );
        }

        $session = $this->stripe()->checkout->sessions->create(
            [
                'mode' => 'payment',
                'line_items' => $lineItems,
                'success_url' => route('reservations.success', [
                    'reservation' => $reservation->id,
                ]),
                'cancel_url' => route('reservations.cancel', [
                    'reservation' => $reservation->id,
                ]),
                'expires_at' => (int) now()->addMinutes(31)->timestamp,
                'metadata' => [
                    'reservation_id' => (string) $reservation->id,
                    'payment_id' => (string) $payment->id,
                ],
                'client_reference_id' => (string) $reservation->id,
            ],
            [
                'idempotency_key' => 'reservation-checkout-'.$payment->id,
            ],
        );

        $payment->update([
            'provider_session_id' => $session->id,
        ]);

        return $session;
    }

    /**
     * Pokušava sigurno isteći Stripe Checkout sesiju.
     */
    public function expireCheckoutSession(Payment $payment): bool
    {
        if (! $payment->provider_session_id) {
            return true;
        }

        try {
            $session = $this->stripe()->checkout->sessions->retrieve(
                $payment->provider_session_id,
            );

            if ($session->status === 'expired') {
                return true;
            }

            if ($session->status !== 'open') {
                return false;
            }

            $expiredSession = $this->stripe()->checkout->sessions->expire(
                $payment->provider_session_id,
            );

            return $expiredSession->status === 'expired';
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * Vraća novac za uplatu preko Stripea.
     *
     * @throws ApiErrorException
     */
    public function refundPayment(
        Payment $payment,
        ?string $paymentIntentId = null,
    ): Refund {
        $paymentIntentId ??= $payment->provider_payment_intent_id;

        if (! is_string($paymentIntentId) || $paymentIntentId === '') {
            throw new \RuntimeException(
                'Nedostaje Stripe Payment Intent za povrat novca.',
            );
        }

        $idempotencyKey = 'reservation-refund-'.$payment->id;

        if ($payment->provider_refund_id) {
            $previousRefund = $this->stripe()->refunds->retrieve(
                $payment->provider_refund_id,
            );

            if (! in_array(
                $previousRefund->status,
                ['failed', 'canceled'],
                true,
            )) {
                return $previousRefund;
            }

            $idempotencyKey .= '-retry-'.$previousRefund->id;
        }

        $refund = $this->stripe()->refunds->create(
            [
                'payment_intent' => $paymentIntentId,
            ],
            [
                'idempotency_key' => $idempotencyKey,
            ],
        );

        $payment->update([
            'provider_payment_intent_id' => $paymentIntentId,
            'provider_refund_id' => $refund->id,
        ]);

        return $refund;
    }
}
