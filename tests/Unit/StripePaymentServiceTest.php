<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\EventSeat;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\User;
use App\Services\StripePaymentService;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

class FakeStripeHttpClient implements ClientInterface
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $requests = [];

    /**
     * @var array<int, array{0: string, 1: int, 2: array<string, string>}|Throwable>
     */
    public array $responses = [];

    /**
     * @param  'delete'|'get'|'post'  $method
     * @param  string  $absUrl
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $params
     * @param  bool  $hasFile
     * @param  'v1'|'v2'  $apiMode
     * @param  int|null  $maxNetworkRetries
     * @return array{0: string, 1: int, 2: array<string, string>}
     */
    public function request(
        $method,
        $absUrl,
        $headers,
        $params,
        $hasFile,
        $apiMode = 'v1',
        $maxNetworkRetries = null,
    ): array {
        $this->requests[] = [
            'method' => $method,
            'url' => $absUrl,
            'headers' => $headers,
            'params' => $params,
            'has_file' => $hasFile,
        ];

        if ($this->responses === []) {
            throw new RuntimeException('Nema pripremljenog Stripe odgovora.');
        }

        $response = array_shift($this->responses);

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }
}

beforeEach(function () {
    config([
        'services.stripe.secret' => 'sk_test_fake_secret',
    ]);

    $this->originalStripeHttpClient = ApiRequestor::httpClient();

    $this->fakeStripeHttpClient = new FakeStripeHttpClient;

    ApiRequestor::setHttpClient($this->fakeStripeHttpClient);
});

afterEach(function () {
    ApiRequestor::setHttpClient($this->originalStripeHttpClient);
});

/**
 * Priprema rezervaciju, događaj, sjedišta i uplatu
 * za testiranje Stripe servisa.
 *
 * @return array<string, mixed>
 */
function createStripeTestReservation(): array
{
    $user = User::factory()->create();

    $category = Category::create([
        'name' => 'Test kategorija',
    ]);

    $event = Event::create([
        'category_id' => $category->id,
        'title' => 'Test događaj',
        'description' => 'Test opis događaja',
        'location' => 'Zenica',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addHours(2),
        'capacity' => 100,
        'seat_rows' => 5,
        'seat_columns' => 10,
        'max_tickets_per_user' => 5,
        'status' => 'published',
    ]);

    $reservation = Reservation::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'status' => Reservation::STATUS_PENDING,
        'total_amount' => 35.50,
        'currency' => 'bam',
        'expires_at' => now()->addMinutes(32),
    ]);

    $seat1 = EventSeat::create([
        'event_id' => $event->id,
        'row_number' => 2,
        'seat_number' => 4,
        'status' => EventSeat::STATUS_RESERVED,
        'reservation_id' => $reservation->id,
    ]);

    $seat2 = EventSeat::create([
        'event_id' => $event->id,
        'row_number' => 2,
        'seat_number' => 5,
        'status' => EventSeat::STATUS_RESERVED,
        'reservation_id' => $reservation->id,
    ]);

    ReservationSeat::create([
        'reservation_id' => $reservation->id,
        'event_seat_id' => $seat1->id,
        'price' => 20.00,
    ]);

    ReservationSeat::create([
        'reservation_id' => $reservation->id,
        'event_seat_id' => $seat2->id,
        'price' => 15.50,
    ]);

    $payment = Payment::create([
        'reservation_id' => $reservation->id,
        'provider' => 'stripe',
        'amount' => 35.50,
        'currency' => 'bam',
        'status' => Payment::STATUS_PENDING,
    ]);

    return [
        'user' => $user,
        'event' => $event,
        'reservation' => $reservation,
        'payment' => $payment,
        'seats' => [$seat1, $seat2],
    ];
}

/**
 * Dodaje simulirani JSON odgovor Stripe API-ja.
 */

/**
 * Dodaje simulirani JSON odgovor Stripe API-ja.
 *
 * @param  array<string, mixed>  $body
 */
function fakeStripeResponse(
    FakeStripeHttpClient $client,
    array $body,
    int $status = 200,
): void {
    $client->responses[] = [
        json_encode($body, JSON_THROW_ON_ERROR),
        $status,
        ['content-type' => 'application/json'],
    ];
}

/*
|--------------------------------------------------------------------------
| Checkout tests
|--------------------------------------------------------------------------
*/

test('kreira checkout sesiju sa ispravnim podacima rezervacije', function () {
    $data = createStripeTestReservation();

    fakeStripeResponse($this->fakeStripeHttpClient, [
        'id' => 'cs_test_fake_123',
        'object' => 'checkout.session',
        'url' => 'https://checkout.stripe.com/c/pay/cs_test_fake_123',
        'mode' => 'payment',
        'status' => 'open',
    ]);

    $service = new StripePaymentService;

    $session = $service->createCheckoutSession(
        $data['reservation'],
        $data['payment'],
    );

    expect($session->id)->toBe('cs_test_fake_123')
        ->and($session->url)->toBe('https://checkout.stripe.com/c/pay/cs_test_fake_123')
        ->and($data['payment']->fresh()->provider_session_id)->toBe('cs_test_fake_123')
        ->and($this->fakeStripeHttpClient->requests)->toHaveCount(1);

    $request = $this->fakeStripeHttpClient->requests[0];

    expect($request['method'])->toBe('post')
        ->and($request['url'])->toContain('/v1/checkout/sessions')
        ->and($request['headers'])
        ->toContain(
            'Idempotency-Key: reservation-checkout-'.$data['payment']->id,
        );

    $params = $request['params'];

    expect($params['mode'])->toBe('payment')
        ->and($params['currency'] ?? null)->toBeNull()
        ->and($params['client_reference_id'])->toBe((string) $data['reservation']->id)
        ->and($params['metadata']['reservation_id'])->toBe((string) $data['reservation']->id)
        ->and($params['metadata']['payment_id'])->toBe((string) $data['payment']->id)
        ->and($params['line_items'])->toHaveCount(2)
        ->and($params['line_items'][0]['price_data']['currency'])->toBe('bam')
        ->and($params['line_items'][0]['price_data']['unit_amount'])->toBe(2000)
        ->and($params['line_items'][0]['price_data']['product_data']['name'])->toBe('Sjedište 4, red 2')
        ->and($params['line_items'][1]['price_data']['unit_amount'])->toBe(1550)
        ->and($params['line_items'][1]['price_data']['product_data']['name'])->toBe('Sjedište 5, red 2')
        ->and($params['line_items'][0]['quantity'])->toBe(1)
        ->and($params['line_items'][1]['quantity'])->toBe(1)
        ->and($params['expires_at'])->toBeGreaterThan(now()->timestamp);
});

test('odbija kreiranje checkout sesije kada rezervacija nema sjedišta', function () {
    $data = createStripeTestReservation();

    ReservationSeat::query()
        ->where('reservation_id', $data['reservation']->id)
        ->delete();

    $service = new StripePaymentService;

    expect(fn () => $service->createCheckoutSession(
        $data['reservation'],
        $data['payment'],
    ))->toThrow(RuntimeException::class, 'Rezervacija nema nijedno sjedište.');

    expect($this->fakeStripeHttpClient->requests)->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Checkout expiration tests
|--------------------------------------------------------------------------
*/

test('uspješno ističe otvorenu checkout sesiju', function () {
    $data = createStripeTestReservation();

    $data['payment']->update([
        'provider_session_id' => 'cs_test_expire_123',
    ]);

    fakeStripeResponse($this->fakeStripeHttpClient, [
        'id' => 'cs_test_expire_123',
        'object' => 'checkout.session',
        'status' => 'open',
    ]);

    fakeStripeResponse($this->fakeStripeHttpClient, [
        'id' => 'cs_test_expire_123',
        'object' => 'checkout.session',
        'status' => 'expired',
    ]);

    $service = new StripePaymentService;

    $result = $service->expireCheckoutSession(
        $data['payment']->fresh(),
    );

    expect($result)->toBeTrue()
        ->and($this->fakeStripeHttpClient->requests)->toHaveCount(2)
        ->and($this->fakeStripeHttpClient->requests[0]['method'])->toBe('get')
        ->and($this->fakeStripeHttpClient->requests[0]['url'])->toContain(
            '/v1/checkout/sessions/cs_test_expire_123',
        )
        ->and($this->fakeStripeHttpClient->requests[1]['method'])->toBe('post')
        ->and($this->fakeStripeHttpClient->requests[1]['url'])->toContain(
            '/v1/checkout/sessions/cs_test_expire_123/expire',
        );
});

test('vraća true kada je checkout sesija već istekla', function () {
    $data = createStripeTestReservation();

    $data['payment']->update([
        'provider_session_id' => 'cs_test_already_expired',
    ]);

    fakeStripeResponse($this->fakeStripeHttpClient, [
        'id' => 'cs_test_already_expired',
        'object' => 'checkout.session',
        'status' => 'expired',
    ]);

    $service = new StripePaymentService;

    $result = $service->expireCheckoutSession(
        $data['payment']->fresh(),
    );

    expect($result)->toBeTrue()
        ->and($this->fakeStripeHttpClient->requests)->toHaveCount(1)
        ->and($this->fakeStripeHttpClient->requests[0]['method'])->toBe('get');
});

test('ne zahtijeva Stripe poziv kada uplata nema checkout sesiju', function () {
    $data = createStripeTestReservation();

    $service = new StripePaymentService;

    $result = $service->expireCheckoutSession(
        $data['payment'],
    );

    expect($result)->toBeTrue()
        ->and($this->fakeStripeHttpClient->requests)->toBeEmpty();
});

test('ne ističe checkout sesiju koja više nije otvorena', function () {
    $data = createStripeTestReservation();

    $data['payment']->update([
        'provider_session_id' => 'cs_test_complete',
    ]);

    fakeStripeResponse($this->fakeStripeHttpClient, [
        'id' => 'cs_test_complete',
        'object' => 'checkout.session',
        'status' => 'complete',
    ]);

    $service = new StripePaymentService;

    $result = $service->expireCheckoutSession(
        $data['payment']->fresh(),
    );

    expect($result)->toBeFalse()
        ->and($this->fakeStripeHttpClient->requests)->toHaveCount(1);
});

test('sigurno vraća false kada Stripe API greška nastane pri isteku sesije', function () {
    $data = createStripeTestReservation();

    $data['payment']->update([
        'provider_session_id' => 'cs_test_error',
    ]);

    $this->fakeStripeHttpClient->responses[] = new RuntimeException(
        'Simulirana Stripe greška.',
    );

    $service = new StripePaymentService;

    $result = $service->expireCheckoutSession(
        $data['payment']->fresh(),
    );

    expect($result)->toBeFalse()
        ->and($this->fakeStripeHttpClient->requests)->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| Refund tests
|--------------------------------------------------------------------------
*/

test('uspješno kreira refund i sačuva Stripe identifikatore', function () {
    $data = createStripeTestReservation();

    $payment = $data['payment'];

    $payment->update([
        'provider_payment_intent_id' => 'pi_test_refund_123',
    ]);

    fakeStripeResponse($this->fakeStripeHttpClient, [
        'id' => 're_test_refund_123',
        'object' => 'refund',
        'payment_intent' => 'pi_test_refund_123',
        'status' => 'succeeded',
        'amount' => 3550,
        'currency' => 'bam',
    ]);

    $service = new StripePaymentService;

    $refund = $service->refundPayment($payment->fresh());

    expect($refund->id)->toBe('re_test_refund_123')
        ->and($refund->status)->toBe('succeeded')
        ->and($payment->fresh()->provider_refund_id)->toBe('re_test_refund_123')
        ->and($payment->fresh()->provider_payment_intent_id)->toBe('pi_test_refund_123')
        ->and($this->fakeStripeHttpClient->requests)->toHaveCount(1);

    $request = $this->fakeStripeHttpClient->requests[0];

    expect($request['method'])->toBe('post')
        ->and($request['url'])->toContain('/v1/refunds')
        ->and($request['params']['payment_intent'])->toBe('pi_test_refund_123');
});

test('odbija refund kada nedostaje Payment Intent ID', function () {
    $data = createStripeTestReservation();

    $service = new StripePaymentService;

    expect(fn () => $service->refundPayment($data['payment']))
        ->toThrow(RuntimeException::class, 'Nedostaje Stripe Payment Intent za povrat novca.');

    expect($this->fakeStripeHttpClient->requests)->toBeEmpty();
});

test('ne kreira novi refund kada postojeći refund nije neuspješan', function () {
    $data = createStripeTestReservation();

    $payment = $data['payment'];

    $payment->update([
        'provider_payment_intent_id' => 'pi_test_existing',
        'provider_refund_id' => 're_test_existing',
    ]);

    fakeStripeResponse($this->fakeStripeHttpClient, [
        'id' => 're_test_existing',
        'object' => 'refund',
        'payment_intent' => 'pi_test_existing',
        'status' => 'pending',
        'amount' => 3550,
        'currency' => 'bam',
    ]);

    $service = new StripePaymentService;

    $refund = $service->refundPayment($payment->fresh());

    expect($refund->id)->toBe('re_test_existing')
        ->and($refund->status)->toBe('pending')
        ->and($this->fakeStripeHttpClient->requests)->toHaveCount(1)
        ->and($this->fakeStripeHttpClient->requests[0]['method'])->toBe('get');
});

test('ponovo pokušava refund kada je prethodni refund neuspješan', function () {
    $data = createStripeTestReservation();

    $payment = $data['payment'];

    $payment->update([
        'provider_payment_intent_id' => 'pi_test_retry',
        'provider_refund_id' => 're_test_failed',
    ]);

    fakeStripeResponse($this->fakeStripeHttpClient, [
        'id' => 're_test_failed',
        'object' => 'refund',
        'payment_intent' => 'pi_test_retry',
        'status' => 'failed',
        'amount' => 3550,
        'currency' => 'bam',
    ]);

    fakeStripeResponse($this->fakeStripeHttpClient, [
        'id' => 're_test_retry_success',
        'object' => 'refund',
        'payment_intent' => 'pi_test_retry',
        'status' => 'succeeded',
        'amount' => 3550,
        'currency' => 'bam',
    ]);

    $service = new StripePaymentService;

    $refund = $service->refundPayment($payment->fresh());

    expect($refund->id)->toBe('re_test_retry_success')
        ->and($refund->status)->toBe('succeeded')
        ->and($payment->fresh()->provider_refund_id)->toBe('re_test_retry_success')
        ->and($this->fakeStripeHttpClient->requests)->toHaveCount(2)
        ->and($this->fakeStripeHttpClient->requests[0]['method'])->toBe('get')
        ->and($this->fakeStripeHttpClient->requests[1]['method'])->toBe('post');
});

test('ponovo pokušava refund kada je prethodni refund otkazan', function () {
    $data = createStripeTestReservation();

    $payment = $data['payment'];

    $payment->update([
        'provider_payment_intent_id' => 'pi_test_retry_cancelled',
        'provider_refund_id' => 're_test_cancelled',
    ]);

    fakeStripeResponse($this->fakeStripeHttpClient, [
        'id' => 're_test_cancelled',
        'object' => 'refund',
        'payment_intent' => 'pi_test_retry_cancelled',
        'status' => 'canceled',
        'amount' => 3550,
        'currency' => 'bam',
    ]);

    fakeStripeResponse($this->fakeStripeHttpClient, [
        'id' => 're_test_retry_after_cancel',
        'object' => 'refund',
        'payment_intent' => 'pi_test_retry_cancelled',
        'status' => 'succeeded',
        'amount' => 3550,
        'currency' => 'bam',
    ]);

    $service = new StripePaymentService;

    $refund = $service->refundPayment($payment->fresh());

    expect($refund->id)->toBe('re_test_retry_after_cancel')
        ->and($refund->status)->toBe('succeeded')
        ->and($payment->fresh()->provider_refund_id)->toBe('re_test_retry_after_cancel')
        ->and($this->fakeStripeHttpClient->requests)->toHaveCount(2);
});
