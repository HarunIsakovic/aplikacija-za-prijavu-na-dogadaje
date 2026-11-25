<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $this->event = Event::create([
        'title' => 'Laravel radionica',
        'description' => 'Ucenje Laravela.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'seat_rows' => 5,
        'seat_columns' => 6,
        'max_tickets_per_user' => 4,
        'image' => null,
        'status' => 'published',
    ]);

    $this->event->categories()->attach($category);

    $this->event->seatPriceGroups()->create([
        'from_row' => 1,
        'to_row' => 5,
        'price' => 20,
    ]);
});

test('korisnik moze napraviti besplatnu rezervaciju', function () {
    $this->event->seatPriceGroups()->update([
        'price' => 0,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('reservations.store', $this->event), [
            'seats' => ['1-1'],
        ]);

    $response->assertRedirect();

    $reservation = Reservation::query()
        ->where('user_id', $this->user->id)
        ->where('event_id', $this->event->id)
        ->firstOrFail();

    expect($reservation->status)
        ->toBe(Reservation::STATUS_CONFIRMED);

    $this->assertDatabaseHas('event_registrations', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
        'attended' => false,
    ]);

    $this->assertDatabaseHas('event_seats', [
        'event_id' => $this->event->id,
        'row_number' => 1,
        'seat_number' => 1,
        'status' => 'sold',
    ]);

    $this->assertDatabaseHas('reservation_seats', [
        'reservation_id' => $reservation->id,
        'price' => 0,
    ]);
});

test('korisnik ne moze napraviti rezervaciju ako vec ima aktivnu prijavu', function () {
    EventRegistration::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('reservations.store', $this->event), [
            'seats' => ['1-1'],
        ]);

    $response->assertSessionHasErrors('seats');

    $this->assertDatabaseMissing('reservations', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

test('korisnik ne moze rezervisati zauzeto sjediste', function () {
    DB::table('event_seats')->insert([
        'event_id' => $this->event->id,
        'row_number' => 1,
        'seat_number' => 1,
        'status' => 'sold',
        'reservation_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('reservations.store', $this->event), [
            'seats' => ['1-1'],
        ]);

    $response->assertSessionHasErrors('seats');

    $this->assertDatabaseMissing('reservations', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

test('korisnik ne moze rezervisati sjediste nakon pocetka dogadjaja', function () {
    $this->event->update([
        'starts_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('reservations.store', $this->event), [
            'seats' => ['1-1'],
        ]);

    $response->assertSessionHasErrors('seats');

    $this->assertDatabaseMissing('reservations', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

test('korisnik ne moze rezervisati vise karata od dozvoljenog broja', function () {
    $response = $this->actingAs($this->user)
        ->post(route('reservations.store', $this->event), [
            'seats' => [
                '1-1',
                '1-2',
                '1-3',
                '1-4',
                '1-5',
            ],
        ]);

    $response->assertSessionHasErrors('seats');

    $this->assertDatabaseMissing('reservations', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

test('korisnik moze otkazati rezervaciju na cekanju', function () {
    $reservation = Reservation::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => Reservation::STATUS_PENDING,
        'total_amount' => 20,
        'currency' => 'bam',
        'expires_at' => now()->addMinutes(32),
    ]);

    Payment::create([
        'reservation_id' => $reservation->id,
        'provider' => 'stripe',
        'amount' => 20,
        'currency' => 'bam',
        'status' => Payment::STATUS_PENDING,
    ]);

    DB::table('event_seats')->insert([
        'event_id' => $this->event->id,
        'row_number' => 1,
        'seat_number' => 1,
        'status' => 'reserved',
        'reservation_id' => $reservation->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->mock(
        StripePaymentService::class,
        function ($mock) {
            $mock->shouldReceive('expireCheckoutSession')
                ->once()
                ->andReturn(true);
        },
    );

    $response = $this->actingAs($this->user)
        ->get(route('reservations.cancel', $reservation));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reservations/cancel')
            ->where('cancelled', true)
        );

    $this->assertDatabaseHas('reservations', [
        'id' => $reservation->id,
        'status' => Reservation::STATUS_CANCELLED,
    ]);

    $this->assertDatabaseHas('payments', [
        'reservation_id' => $reservation->id,
        'status' => Payment::STATUS_FAILED,
    ]);

    $this->assertDatabaseHas('event_seats', [
        'event_id' => $this->event->id,
        'row_number' => 1,
        'seat_number' => 1,
        'status' => 'available',
        'reservation_id' => null,
    ]);
});

test('korisnik moze vidjeti svoje prijave', function () {
    $registration = EventRegistration::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('registrations.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('registrations/index')
        ->has('registrations.data', 1)
        ->where('registrations.data.0.id', $registration->id)
        ->where('registrations.total', 1)
    );
});

test('korisnik ne moze vidjeti prijave drugih korisnika', function () {
    $otherUser = User::factory()->create();

    EventRegistration::create([
        'user_id' => $otherUser->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('registrations.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('registrations/index')
        ->has('registrations.data', 0)
        ->where('registrations.total', 0)
    );
});

test('korisnik moze pretrazivati svoje prijave po nazivu dogadjaja', function () {
    $secondCategory = Category::create([
        'name' => 'Konferencija',
    ]);

    $secondEvent = Event::create([
        'title' => 'React konferencija',
        'description' => 'Opis konferencije.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 50,
        'image' => null,
        'status' => 'published',
    ]);

    $secondEvent->categories()->attach($secondCategory);

    EventRegistration::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
    ]);

    EventRegistration::create([
        'user_id' => $this->user->id,
        'event_id' => $secondEvent->id,
        'status' => 'registered',
    ]);

    $this->actingAs($this->user)
        ->get(route('registrations.index', ['search' => 'React']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('registrations.data', 1)
            ->where('registrations.data.0.event.title', 'React konferencija')
            ->where('registrations.total', 1)
        );
});
