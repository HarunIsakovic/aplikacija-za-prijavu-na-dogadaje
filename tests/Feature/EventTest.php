<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('korisnik moze vidjeti objavljene buduce dogadaje', function () {
    $user = User::factory()->create();

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $publishedEvent = Event::create([
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

    Event::create([
        'category_id' => $category->id,
        'title' => 'Nacrt radionica',
        'description' => 'Neobjavljeni dogadaj.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 20,
        'image' => null,
        'status' => 'draft',
    ]);

    Event::create([
        'category_id' => $category->id,
        'title' => 'Prosli dogadaj',
        'description' => 'Dogadaj koji je vec poceo.',
        'location' => 'Zenica',
        'starts_at' => now()->subDays(2),
        'ends_at' => null,
        'capacity' => 20,
        'image' => null,
        'status' => 'published',
    ]);

    $response = $this->actingAs($user)->get(route('events.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/index')
            ->has('events', 1)
            ->where('events.0.id', $publishedEvent->id)
            ->where('events.0.title', 'Laravel radionica')
        );
});

test('neprijavljeni korisnik ne moze pregledati dogadaje', function () {
    $response = $this->get(route('events.index'));

    $response->assertRedirect(route('login'));
});


test('korisnik moze filtrirati dogadaje po kategoriji', function () {
    $user = User::factory()->create();

    $workshop = Category::create([
        'name' => 'Radionica',
    ]);

    $conference = Category::create([
        'name' => 'Konferencija',
    ]);

    $workshopEvent = Event::create([
        'category_id' => $workshop->id,
        'title' => 'Laravel radionica',
        'description' => 'Opis radionice.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    Event::create([
        'category_id' => $conference->id,
        'title' => 'PHP konferencija',
        'description' => 'Opis konferencije.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 50,
        'image' => null,
        'status' => 'published',
    ]);

    $response = $this->actingAs($user)->get(route('events.index', [
        'category_id' => $workshop->id,
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.id', $workshopEvent->id)
        );
});


test('korisnik moze filtrirati dogadaje po tagu', function () {
    $user = User::factory()->create();

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $onlineTag = Tag::create([
        'name' => 'Online',
    ]);

    $freeTag = Tag::create([
        'name' => 'Besplatno',
    ]);

    $onlineEvent = Event::create([
        'category_id' => $category->id,
        'title' => 'Online Laravel radionica',
        'description' => 'Radionica putem interneta.',
        'location' => 'Online',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $freeEvent = Event::create([
        'category_id' => $category->id,
        'title' => 'Besplatna PHP radionica',
        'description' => 'Besplatna radionica.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 20,
        'image' => null,
        'status' => 'published',
    ]);

    $onlineEvent->tags()->attach($onlineTag);
    $freeEvent->tags()->attach($freeTag);

    $response = $this->actingAs($user)->get(route('events.index', [
        'tag_id' => $onlineTag->id,
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.id', $onlineEvent->id)
        );
});


test('korisnik moze vidjeti detalje objavljenog buduceg dogadaja', function () {
    $user = User::factory()->create();

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $event = Event::create([
        'category_id' => $category->id,
        'title' => 'Laravel radionica',
        'description' => 'Detaljan opis radionice.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $event->registrations()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'registered',
        'attended' => false,
    ]);

    $response = $this->actingAs($user)->get(route('events.show', $event));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/show')
            ->where('event.id', $event->id)
            ->where('event.title', 'Laravel radionica')
            ->where('event.registered_count', 1)
            ->where('event.capacity', 30)
        );
});

test('korisnik ne moze vidjeti detalje neobjavljenog dogadaja', function () {
    $user = User::factory()->create();

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $event = Event::create([
        'category_id' => $category->id,
        'title' => 'Skrivena radionica',
        'description' => 'Neobjavljeni dogadaj.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->get(route('events.show', $event))
        ->assertNotFound();
});

test('korisnik ne moze vidjeti detalje dogadaja koji je vec poceo', function () {
    $user = User::factory()->create();

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $event = Event::create([
        'category_id' => $category->id,
        'title' => 'Prosla radionica',
        'description' => 'Dogadaj koji je vec poceo.',
        'location' => 'Zenica',
        'starts_at' => now()->subDay(),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $this->actingAs($user)
        ->get(route('events.show', $event))
        ->assertNotFound();
});

test('neprijavljeni korisnik ne moze vidjeti detalje dogadaja', function () {
    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $event = Event::create([
        'category_id' => $category->id,
        'title' => 'Laravel radionica',
        'description' => 'Opis radionice.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $this->get(route('events.show', $event))
        ->assertRedirect(route('login'));
});