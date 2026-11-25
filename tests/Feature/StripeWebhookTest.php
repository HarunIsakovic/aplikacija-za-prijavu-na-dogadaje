<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventSeat;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Stripe\Refund;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set(
            'services.stripe.webhook_secret',
            $this->webhookSecret,
        );
    }

    private function createReservation(): array
    {
        $user = User::factory()->create();

        $category = Category::create([
            'name' => 'Test kategorija',
        ]);

        $event = Event::create([
            'title' => 'Test događaj',
            'description' => 'Opis testnog događaja',
            'location' => 'Zenica',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(6),
            'capacity' => 10,
            'seat_rows' => 2,
            'seat_columns' => 5,
            'max_tickets_per_user' => 4,
            'status' => 'published',
        ]);

        $event->categories()->attach($category);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => Reservation::STATUS_PENDING,
            'total_amount' => 20,
            'currency' => 'bam',
            'expires_at' => now()->addMinutes(32),
        ]);

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'provider' => 'stripe',
            'provider_session_id' => 'cs_test_123',
            'amount' => 20,
            'currency' => 'bam',
            'status' => Payment::STATUS_PENDING,
        ]);

        $seat = EventSeat::create([
            'event_id' => $event->id,
            'row_number' => 1,
            'seat_number' => 1,
            'status' => EventSeat::STATUS_RESERVED,
            'reservation_id' => $reservation->id,
        ]);

        ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'event_seat_id' => $seat->id,
            'price' => 20,
        ]);

        return compact(
            'user',
            'event',
            'reservation',
            'payment',
            'seat',
        );
    }

    private function sendWebhook(
        string $eventId,
        string $eventType,
        array $object,
    ) {
        $payload = json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => $eventType,
            'data' => [
                'object' => $object,
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();

        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            $this->webhookSecret,
        );

        return $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload,
        );
    }

    public function test_neispravan_stripe_potpis_se_odbija(): void
    {
        $response = $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=123,v1=neispravan',
            ],
            '{"id":"evt_invalid","type":"checkout.session.completed"}',
        );

        $response->assertBadRequest();

        $this->assertDatabaseMissing('stripe_webhook_events', [
            'stripe_event_id' => 'evt_invalid',
        ]);
    }

    public function test_uspjesna_uplata_potvrdjuje_rezervaciju(): void
    {
        $data = $this->createReservation();

        $response = $this->sendWebhook(
            'evt_success_123',
            'checkout.session.completed',
            [
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
                'metadata' => [
                    'reservation_id' => (string) $data['reservation']->id,
                    'payment_id' => (string) $data['payment']->id,
                ],
                'payment_status' => 'paid',
                'amount_total' => 2000,
                'currency' => 'bam',
                'payment_intent' => 'pi_test_123',
            ],
        );

        $response->assertOk();

        $this->assertDatabaseHas('reservations', [
            'id' => $data['reservation']->id,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $data['payment']->id,
            'status' => Payment::STATUS_PAID,
            'provider_payment_intent_id' => 'pi_test_123',
        ]);

        $this->assertDatabaseHas('event_seats', [
            'id' => $data['seat']->id,
            'status' => EventSeat::STATUS_SOLD,
        ]);

        $this->assertDatabaseHas('event_registrations', [
            'user_id' => $data['user']->id,
            'event_id' => $data['event']->id,
            'status' => 'registered',
        ]);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_success_123',
            'status' => StripeWebhookEvent::STATUS_PROCESSED,
        ]);
    }

    public function test_ponovljeni_webhook_ne_aktivira_duplu_prijavu(): void
    {
        $data = $this->createReservation();

        $payload = [
            'id' => 'cs_test_123',
            'object' => 'checkout.session',
            'metadata' => [
                'reservation_id' => (string) $data['reservation']->id,
                'payment_id' => (string) $data['payment']->id,
            ],
            'payment_status' => 'paid',
            'amount_total' => 2000,
            'currency' => 'bam',
            'payment_intent' => 'pi_test_123',
        ];

        $this->sendWebhook(
            'evt_duplicate_123',
            'checkout.session.completed',
            $payload,
        )->assertOk();

        $this->sendWebhook(
            'evt_duplicate_123',
            'checkout.session.completed',
            $payload,
        )->assertOk();

        $this->assertSame(
            1,
            EventRegistration::query()
                ->where('user_id', $data['user']->id)
                ->where('event_id', $data['event']->id)
                ->count(),
        );

        $this->assertSame(
            1,
            StripeWebhookEvent::query()
                ->where('stripe_event_id', 'evt_duplicate_123')
                ->count(),
        );
    }

    public function test_uplata_sa_neispravnim_iznosom_se_ne_potvrdjuje(): void
    {
        $data = $this->createReservation();

        $response = $this->sendWebhook(
            'evt_wrong_amount_123',
            'checkout.session.completed',
            [
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
                'metadata' => [
                    'reservation_id' => (string) $data['reservation']->id,
                    'payment_id' => (string) $data['payment']->id,
                ],
                'payment_status' => 'paid',
                'amount_total' => 1000,
                'currency' => 'bam',
                'payment_intent' => 'pi_test_123',
            ],
        );

        $response->assertStatus(500);

        $this->assertDatabaseHas('reservations', [
            'id' => $data['reservation']->id,
            'status' => Reservation::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $data['payment']->id,
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    public function test_istekla_checkout_sesija_oslobadja_sjediste(): void
    {
        $data = $this->createReservation();

        $response = $this->sendWebhook(
            'evt_expired_123',
            'checkout.session.expired',
            [
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
                'metadata' => [
                    'reservation_id' => (string) $data['reservation']->id,
                    'payment_id' => (string) $data['payment']->id,
                ],
                'payment_status' => 'unpaid',
                'amount_total' => 2000,
                'currency' => 'bam',
            ],
        );

        $response->assertOk();

        $this->assertDatabaseHas('reservations', [
            'id' => $data['reservation']->id,
            'status' => Reservation::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $data['payment']->id,
            'status' => Payment::STATUS_FAILED,
        ]);

        $this->assertDatabaseHas('event_seats', [
            'id' => $data['seat']->id,
            'status' => EventSeat::STATUS_AVAILABLE,
            'reservation_id' => null,
        ]);
    }

    public function test_naknadno_potvrdjen_refund_azurira_status_uplate(): void
    {
        $data = $this->createReservation();

        $data['payment']->update([
            'status' => Payment::STATUS_PAID,
            'provider_payment_intent_id' => 'pi_test_123',
            'provider_refund_id' => 're_test_123',
        ]);

        $response = $this->sendWebhook(
            'evt_refund_updated_123',
            'refund.updated',
            [
                'id' => 're_test_123',
                'object' => 'refund',
                'payment_intent' => 'pi_test_123',
                'status' => 'succeeded',
                'amount' => 2000,
                'currency' => 'bam',
            ],
        );

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $data['payment']->id,
            'status' => Payment::STATUS_REFUNDED,
            'provider_refund_id' => 're_test_123',
        ]);

        $payment = Payment::query()->findOrFail($data['payment']->id);

        $this->assertNotNull($payment->refunded_at);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_refund_updated_123',
            'status' => StripeWebhookEvent::STATUS_PROCESSED,
        ]);
    }

    public function test_kasna_uplata_nakon_isteka_rezervacije_pokrece_refund(): void
    {
        $data = $this->createReservation();

        // Prvo simuliramo istek rezervacije.
        $this->sendWebhook(
            'evt_expired_before_late_payment',
            'checkout.session.expired',
            [
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
                'metadata' => [
                    'reservation_id' => (string) $data['reservation']->id,
                    'payment_id' => (string) $data['payment']->id,
                ],
                'payment_status' => 'unpaid',
                'amount_total' => 2000,
                'currency' => 'bam',
            ],
        )->assertOk();

        // Simuliramo Stripe servis koji odmah uspješno vraća novac.
        $refund = Refund::constructFrom([
            'id' => 're_late_payment_123',
            'object' => 'refund',
            'status' => 'succeeded',
            'amount' => 2000,
            'currency' => 'bam',
        ]);

        $stripePaymentService = \Mockery::mock(
            StripePaymentService::class,
        );

        $stripePaymentService
            ->shouldReceive('refundPayment')
            ->once()
            ->withArgs(function (
                Payment $payment,
                ?string $paymentIntentId,
            ) use ($data): bool {
                return $payment->id === $data['payment']->id
                    && $paymentIntentId === 'pi_late_payment_123';
            })
            ->andReturn($refund);

        $this->app->instance(
            StripePaymentService::class,
            $stripePaymentService,
        );

        // Stripe naknadno potvrđuje uspješnu uplatu.
        $response = $this->sendWebhook(
            'evt_late_payment_123',
            'checkout.session.completed',
            [
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
                'metadata' => [
                    'reservation_id' => (string) $data['reservation']->id,
                    'payment_id' => (string) $data['payment']->id,
                ],
                'payment_status' => 'paid',
                'amount_total' => 2000,
                'currency' => 'bam',
                'payment_intent' => 'pi_late_payment_123',
            ],
        );

        $response->assertOk();

        $this->assertDatabaseHas('reservations', [
            'id' => $data['reservation']->id,
            'status' => Reservation::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $data['payment']->id,
            'status' => Payment::STATUS_REFUNDED,
            'provider_payment_intent_id' => 'pi_late_payment_123',
            'provider_refund_id' => 're_late_payment_123',
        ]);

        $this->assertDatabaseHas('event_seats', [
            'id' => $data['seat']->id,
            'status' => EventSeat::STATUS_AVAILABLE,
            'reservation_id' => null,
        ]);

        $this->assertDatabaseMissing('event_registrations', [
            'user_id' => $data['user']->id,
            'event_id' => $data['event']->id,
        ]);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_late_payment_123',
            'status' => StripeWebhookEvent::STATUS_PROCESSED,
            'payment_id' => $data['payment']->id,
        ]);
    }
}
