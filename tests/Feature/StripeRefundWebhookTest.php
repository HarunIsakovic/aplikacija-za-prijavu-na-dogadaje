<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StripeRefundWebhookTest extends TestCase
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

    private function createPaidPayment(): Payment
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
            'status' => Reservation::STATUS_CANCELLED,
            'total_amount' => 20,
            'currency' => 'bam',
            'expires_at' => now()->subMinutes(5),
            'cancelled_at' => now(),
        ]);

        return Payment::create([
            'reservation_id' => $reservation->id,
            'provider' => 'stripe',
            'provider_session_id' => 'cs_test_123',
            'provider_payment_intent_id' => 'pi_test_123',
            'provider_refund_id' => 're_test_123',
            'amount' => 20,
            'currency' => 'bam',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    private function sendRefundWebhook(
        string $eventId,
        string $refundId,
        string $paymentIntentId,
        string $status,
        int $amount,
        string $currency = 'bam',
    ) {
        $payload = json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => 'refund.updated',
            'data' => [
                'object' => [
                    'id' => $refundId,
                    'object' => 'refund',
                    'payment_intent' => $paymentIntentId,
                    'status' => $status,
                    'amount' => $amount,
                    'currency' => $currency,
                ],
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

    public function test_neuspjeli_refund_ne_oznacava_uplatu_kao_vracenu(): void
    {
        $payment = $this->createPaidPayment();

        $response = $this->sendRefundWebhook(
            'evt_refund_failed_123',
            're_test_123',
            'pi_test_123',
            'failed',
            2000,
        );

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PAID,
            'provider_refund_id' => 're_test_123',
        ]);

        $this->assertDatabaseMissing('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_REFUNDED,
        ]);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_refund_failed_123',
            'status' => StripeWebhookEvent::STATUS_PROCESSED,
            'payment_id' => $payment->id,
        ]);
    }

    public function test_refund_sa_neispravnim_iznosom_se_odbija(): void
    {
        $payment = $this->createPaidPayment();

        $response = $this->sendRefundWebhook(
            'evt_refund_wrong_amount_123',
            're_test_123',
            'pi_test_123',
            'succeeded',
            1000,
        );

        $response->assertStatus(500);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PAID,
            'provider_refund_id' => 're_test_123',
        ]);

        $this->assertDatabaseMissing('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_REFUNDED,
        ]);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_refund_wrong_amount_123',
            'status' => StripeWebhookEvent::STATUS_FAILED,
            'payment_id' => null,
        ]);
    }

    public function test_refund_sa_neispravnom_valutom_se_odbija(): void
    {
        $payment = $this->createPaidPayment();

        $response = $this->sendRefundWebhook(
            'evt_refund_wrong_currency_123',
            're_test_123',
            'pi_test_123',
            'succeeded',
            2000,
            'eur',
        );

        $response->assertStatus(500);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PAID,
            'provider_refund_id' => 're_test_123',
        ]);

        $this->assertDatabaseMissing('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_REFUNDED,
        ]);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_refund_wrong_currency_123',
            'status' => StripeWebhookEvent::STATUS_FAILED,
            'payment_id' => null,
        ]);
    }

    public function test_refund_sa_drugim_payment_intentom_se_odbija(): void
    {
        $payment = $this->createPaidPayment();

        $response = $this->sendRefundWebhook(
            'evt_refund_wrong_intent_123',
            're_test_123',
            'pi_other_123',
            'succeeded',
            2000,
        );

        $response->assertStatus(500);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PAID,
            'provider_payment_intent_id' => 'pi_test_123',
        ]);

        $this->assertDatabaseMissing('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_REFUNDED,
        ]);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_refund_wrong_intent_123',
            'status' => StripeWebhookEvent::STATUS_FAILED,
            'payment_id' => null,
        ]);
    }

    public function test_uspjesan_refund_oznacava_uplatu_kao_vracenu(): void
    {
        $payment = $this->createPaidPayment();

        $response = $this->sendRefundWebhook(
            'evt_refund_succeeded_123',
            're_test_123',
            'pi_test_123',
            'succeeded',
            2000,
        );

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_REFUNDED,
            'provider_refund_id' => 're_test_123',
        ]);

        $this->assertNotNull(
            $payment->fresh()->refunded_at,
        );

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_refund_succeeded_123',
            'status' => StripeWebhookEvent::STATUS_PROCESSED,
            'payment_id' => $payment->id,
        ]);
    }

    public function test_refund_na_cekanju_ne_oznacava_uplatu_kao_vracenu(): void
    {
        $payment = $this->createPaidPayment();

        $response = $this->sendRefundWebhook(
            'evt_refund_pending_123',
            're_test_123',
            'pi_test_123',
            'pending',
            2000,
        );

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PAID,
            'provider_refund_id' => 're_test_123',
        ]);

        $this->assertNull(
            $payment->fresh()->refunded_at,
        );

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_refund_pending_123',
            'status' => StripeWebhookEvent::STATUS_PROCESSED,
            'payment_id' => $payment->id,
        ]);
    }

    public function test_nepoznata_refundacija_se_odbija(): void
    {
        $this->createPaidPayment();

        $response = $this->sendRefundWebhook(
            'evt_refund_unknown_123',
            're_unknown_123',
            'pi_unknown_123',
            'succeeded',
            2000,
        );

        $response->assertStatus(500);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_refund_unknown_123',
            'status' => StripeWebhookEvent::STATUS_FAILED,
            'payment_id' => null,
        ]);

        $this->assertDatabaseMissing('payments', [
            'provider_refund_id' => 're_unknown_123',
            'status' => Payment::STATUS_REFUNDED,
        ]);
    }

    public function test_ponovljeni_refund_webhook_ne_obradjuje_se_ponovo(): void
    {
        $payment = $this->createPaidPayment();

        $eventId = 'evt_refund_duplicate_123';

        $firstResponse = $this->sendRefundWebhook(
            $eventId,
            're_test_123',
            'pi_test_123',
            'succeeded',
            2000,
        );

        $firstResponse->assertOk();

        $secondResponse = $this->sendRefundWebhook(
            $eventId,
            're_test_123',
            'pi_test_123',
            'succeeded',
            2000,
        );

        $secondResponse->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_REFUNDED,
            'provider_refund_id' => 're_test_123',
        ]);

        $this->assertDatabaseCount('stripe_webhook_events', 1);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => $eventId,
            'status' => StripeWebhookEvent::STATUS_PROCESSED,
            'payment_id' => $payment->id,
        ]);
    }
}
