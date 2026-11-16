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
            ->has('events.data', 1)
            ->where('events.data.0.id', $publishedEvent->id)
            ->where('events.data.0.title', 'Laravel radionica')
            ->where('events.total', 1)
            ->where('events.per_page', 9)
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
        'category_ids' => [$workshop->id],
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.id', $workshopEvent->id)
            ->where('events.total', 1)
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
        'tag_ids' => [$onlineTag->id],
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.id', $onlineEvent->id)
            ->where('events.total', 1)
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

test('korisnik moze pretrazivati dogadaje po nazivu', function () {
    $category = Category::create([
        'name' => 'Radionica',
    ]);

    Event::create([
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
        'title' => 'React konferencija',
        'description' => 'Ucenje Reacta.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 50,
        'image' => null,
        'status' => 'published',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('events.index', ['search' => 'Laravel']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.title', 'Laravel radionica')
            ->where('events.total', 1)
        );
});

test('korisnik moze kombinovati vise filtera kategorija i tagova', function () {
    $user = User::factory()->create();

    $workshop = Category::create(['name' => 'Radionica']);
    $conference = Category::create(['name' => 'Konferencija']);
    $onlineTag = Tag::create(['name' => 'Online']);
    $freeTag = Tag::create(['name' => 'Besplatno']);

    $event1 = Event::create([
        'category_id' => $workshop->id,
        'title' => 'Laravel online radionica',
        'description' => 'Opis događaja.',
        'location' => 'Online',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $event2 = Event::create([
        'category_id' => $conference->id,
        'title' => 'React konferencija',
        'description' => 'Opis događaja.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 50,
        'image' => null,
        'status' => 'published',
    ]);

    $event3 = Event::create([
        'category_id' => $workshop->id,
        'title' => 'PHP radionica',
        'description' => 'Opis događaja.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(12),
        'ends_at' => null,
        'capacity' => 20,
        'image' => null,
        'status' => 'published',
    ]);

    $event1->tags()->attach($onlineTag);
    $event2->tags()->attach($freeTag);
    $event3->tags()->attach($freeTag);

    $this->actingAs($user)
        ->get(route('events.index', [
            'category_ids' => [$workshop->id, $conference->id],
            'tag_ids' => [$onlineTag->id, $freeTag->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 3)
            ->where('events.total', 3)
        );
});

test('admin moze pretrazivati dogadaje po nazivu', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    Event::create([
        'category_id' => $category->id,
        'title' => 'Laravel radionica',
        'description' => 'Radionica o Laravelu.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'draft',
    ]);

    Event::create([
        'category_id' => $category->id,
        'title' => 'React konferencija',
        'description' => 'Konferencija o Reactu.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 50,
        'image' => null,
        'status' => 'draft',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.events.index', [
            'search' => 'Laravel',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.title', 'Laravel radionica')
            ->where('events.total', 1)
        );
});
