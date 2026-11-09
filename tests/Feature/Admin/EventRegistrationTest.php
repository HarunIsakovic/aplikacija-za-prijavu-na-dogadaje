<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->category = Category::create([
        'name' => 'Radionica',
    ]);

    $this->event = Event::create([
        'category_id' => $this->category->id,
        'title' => 'Testni događaj',
        'description' => 'Opis događaja.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);
});

test('admin moze vidjeti prijavljene korisnike za dogadaj', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    EventRegistration::create([
        'user_id' => $user->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
        'attended' => false,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.events.registrations.index', $this->event));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/registrations/index')
            ->has('registrations', 1)
            ->where('registrations.0.user.id', $user->id)
        );
});

test('obicni korisnik ne moze vidjeti prijavljene korisnike u administraciji', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $response = $this->actingAs($user)
        ->get(route('admin.events.registrations.index', $this->event));

    $response->assertForbidden();
});

test('admin moze evidentirati prisustvo korisnika', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $registration = EventRegistration::create([
        'user_id' => $user->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
        'attended' => false,
    ]);

    $response = $this->actingAs($this->admin)
        ->put(route('admin.registrations.attendance.update', $registration), [
            'attended' => true,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('event_registrations', [
        'id' => $registration->id,
        'attended' => true,
    ]);
});

test('obicni korisnik ne moze evidentirati prisustvo', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $registration = EventRegistration::create([
        'user_id' => $user->id,
        'event_id' => $this->event->id,
        'status' => 'registered',
        'attended' => false,
    ]);

    $response = $this->actingAs($user)
        ->put(route('admin.registrations.attendance.update', $registration), [
            'attended' => true,
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('event_registrations', [
        'id' => $registration->id,
        'attended' => false,
    ]);
});

test('admin ne moze evidentirati prisustvo za otkazanu prijavu', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $registration = EventRegistration::create([
        'user_id' => $user->id,
        'event_id' => $this->event->id,
        'status' => 'cancelled',
        'attended' => false,
    ]);

    $response = $this->actingAs($this->admin)
        ->put(route('admin.registrations.attendance.update', $registration), [
            'attended' => true,
        ]);

    $response->assertSessionHasErrors(['attendance']);

    $this->assertDatabaseHas('event_registrations', [
        'id' => $registration->id,
        'status' => 'cancelled',
        'attended' => false,
    ]);
});
