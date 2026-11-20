<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->event = Event::create([
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

test('prijavljeni korisnik moze dodati buduci objavljeni dogadaj u omiljene', function () {
    $response = $this->actingAs($this->user)
        ->post(route('events.favorites.store', $this->event));

    $response->assertRedirect();

    $this->assertDatabaseHas('event_favorites', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

test('korisnik ne moze dodati isti dogadaj vise puta u omiljene', function () {
    $this->actingAs($this->user)
        ->post(route('events.favorites.store', $this->event));

    $this->actingAs($this->user)
        ->post(route('events.favorites.store', $this->event));

    expect(
        $this->user->favorites()
            ->where('events.id', $this->event->id)
            ->count()
    )->toBe(1);
});

test('korisnik ne moze dodati neobjavljeni dogadaj u omiljene', function () {
    $this->event->update([
        'status' => 'draft',
    ]);

    $this->actingAs($this->user)
        ->post(route('events.favorites.store', $this->event))
        ->assertNotFound();

    $this->assertDatabaseMissing('event_favorites', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

test('korisnik ne moze dodati dogadaj koji je vec poceo u omiljene', function () {
    $this->event->update([
        'starts_at' => now()->subDay(),
    ]);

    $this->actingAs($this->user)
        ->post(route('events.favorites.store', $this->event))
        ->assertNotFound();

    $this->assertDatabaseMissing('event_favorites', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

test('korisnik moze ukloniti dogadaj iz omiljenih', function () {
    $this->user->favorites()->attach($this->event);

    $response = $this->actingAs($this->user)
        ->delete(route('events.favorites.destroy', $this->event));

    $response->assertRedirect();

    $this->assertDatabaseMissing('event_favorites', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

test('korisnik moze vidjeti samo svoje omiljene dogadaje', function () {
    $otherUser = User::factory()->create();

    $otherEvent = Event::create([
        'title' => 'React konferencija',
        'description' => 'Opis konferencije.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 50,
        'image' => null,
        'status' => 'published',
    ]);

    $this->user->favorites()->attach($this->event);
    $otherUser->favorites()->attach($otherEvent);

    $this->actingAs($this->user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('favorites/index')
            ->has('favorites', 1)
            ->where('favorites.0.id', $this->event->id)
        );
});

test('gost ne moze pristupiti listi omiljenih dogadaja', function () {
    $this->get(route('favorites.index'))
        ->assertRedirect(route('login'));
});

test('gost ne moze dodavati dogadaje u omiljene', function () {
    $this->post(route('events.favorites.store', $this->event))
        ->assertRedirect(route('login'));
});

test('gost ne moze uklanjati dogadaje iz omiljenih', function () {
    $this->delete(route('events.favorites.destroy', $this->event))
        ->assertRedirect(route('login'));
});

test('lista dogadaja prikazuje status omiljenog dogadaja', function () {
    $this->user->favorites()->attach($this->event);

    $this->actingAs($this->user)
        ->get(route('events.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_favorite', true)
            ->where('events.data.0.is_favorite', true)
        );
});

test('detalji dogadaja prikazuju status omiljenog dogadaja', function () {
    $this->user->favorites()->attach($this->event);

    $this->actingAs($this->user)
        ->get(route('events.show', $this->event->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_favorite', true)
            ->where('event.is_favorite', true)
        );
});

test('buduci objavljeni omiljeni dogadaj sa slobodnim mjestima dozvoljava prijavu', function () {
    $this->user->favorites()->attach($this->event);

    $this->actingAs($this->user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('favorites.0.can_register', true)
        );
});

test('omiljeni dogadaj koji je poceo ne dozvoljava prijavu', function () {
    $this->event->update([
        'starts_at' => now()->subDay(),
    ]);

    $this->user->favorites()->attach($this->event);

    $this->actingAs($this->user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('favorites.0.can_register', false)
        );
});

test('popunjeni omiljeni dogadaj ne dozvoljava prijavu', function () {
    $this->event->update([
        'capacity' => 1,
    ]);

    $otherUser = User::factory()->create();

    $this->event->registrations()->create([
        'user_id' => $otherUser->id,
        'status' => 'registered',
    ]);

    $this->user->favorites()->attach($this->event);

    $this->actingAs($this->user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('favorites.0.can_register', false)
        );
});
