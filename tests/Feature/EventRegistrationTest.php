<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $this->event = Event::create([
        'category_id' => $category->id,
        'title' => 'Laravel radionica',
        'description' => 'Ucenje Laravela.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);
});

test('korisnik se moze prijaviti na objavljeni dogadaj', function () {
    $response = $this->actingAs($this->user)
        ->post(route('events.registrations.store', $this->event));

    $response->assertRedirect();

    $this->assertDatabaseHas('event_registrations', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
        'attended' => false,
    ]);
});

test('korisnik ne moze napraviti duplu aktivnu prijavu', function () {
    EventRegistration::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('events.registrations.store', $this->event));

    $response->assertSessionHasErrors('registration');

    expect(
        EventRegistration::where('user_id', $this->user->id)
            ->where('event_id', $this->event->id)
            ->where('status', 'registered')
            ->count()
    )->toBe(1);
});

test('korisnik se ne moze prijaviti kada je kapacitet popunjen', function () {
    $this->event->update([
        'capacity' => 1,
    ]);

    $otherUser = User::factory()->create();

    EventRegistration::create([
        'user_id' => $otherUser->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('events.registrations.store', $this->event));

    $response->assertSessionHasErrors('registration');

    $this->assertDatabaseMissing('event_registrations', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
    ]);
});

test('korisnik se ne moze prijaviti na dogadaj koji je poceo', function () {
    $this->event->update([
        'starts_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('events.registrations.store', $this->event));

    $response->assertSessionHasErrors('registration');

    $this->assertDatabaseMissing('event_registrations', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

test('korisnik moze otkazati svoju prijavu', function () {
    EventRegistration::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('events.registrations.destroy', $this->event));

    $response->assertRedirect();

    $this->assertDatabaseHas('event_registrations', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => 'cancelled',
    ]);
});

test('korisnik se moze ponovo prijaviti nakon otkazivanja', function () {
    EventRegistration::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => 'cancelled',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('events.registrations.store', $this->event));

    $response->assertRedirect();

    $this->assertDatabaseHas('event_registrations', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
    ]);

    expect(
        EventRegistration::where('user_id', $this->user->id)
            ->where('event_id', $this->event->id)
            ->count()
    )->toBe(1);
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
        ->has('registrations', 1)
        ->where('registrations.0.id', $registration->id)
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
        ->has('registrations', 0)
    );
});
