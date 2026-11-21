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
        'title' => 'Laravel radionica',
        'description' => 'Ucenje Laravela.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $publishedEvent->categories()->attach($category);

    $draftEvent = Event::create([
        'title' => 'Nacrt radionica',
        'description' => 'Neobjavljeni dogadaj.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 20,
        'image' => null,
        'status' => 'draft',
    ]);

    $draftEvent->categories()->attach($category);

    $pastEvent = Event::create([
        'title' => 'Prosli dogadaj',
        'description' => 'Dogadaj koji je vec poceo.',
        'location' => 'Zenica',
        'starts_at' => now()->subDays(2),
        'ends_at' => null,
        'capacity' => 20,
        'image' => null,
        'status' => 'published',
    ]);

    $pastEvent->categories()->attach($category);

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

test('neprijavljeni korisnik moze pregledati dogadaje', function () {
    $response = $this->get(route('events.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/index')
        );
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
        'title' => 'Laravel radionica',
        'description' => 'Opis radionice.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $workshopEvent->categories()->attach($workshop);

    $conferenceEvent = Event::create([
        'title' => 'PHP konferencija',
        'description' => 'Opis konferencije.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 50,
        'image' => null,
        'status' => 'published',
    ]);

    $conferenceEvent->categories()->attach($conference);

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
        'title' => 'Online Laravel radionica',
        'description' => 'Radionica putem interneta.',
        'location' => 'Online',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $onlineEvent->categories()->attach($category);

    $freeEvent = Event::create([
        'title' => 'Besplatna PHP radionica',
        'description' => 'Besplatna radionica.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 20,
        'image' => null,
        'status' => 'published',
    ]);

    $freeEvent->categories()->attach($category);

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
        'title' => 'Laravel radionica',
        'description' => 'Detaljan opis radionice.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $event->categories()->attach($category);

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
        'title' => 'Skrivena radionica',
        'description' => 'Neobjavljeni dogadaj.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'draft',
    ]);

    $event->categories()->attach($category);

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
        'title' => 'Prosla radionica',
        'description' => 'Dogadaj koji je vec poceo.',
        'location' => 'Zenica',
        'starts_at' => now()->subDay(),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $event->categories()->attach($category);

    $this->actingAs($user)
        ->get(route('events.show', $event))
        ->assertNotFound();
});

test('neprijavljeni korisnik moze vidjeti detalje objavljenog buduceg dogadaja', function () {
    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $event = Event::create([
        'title' => 'Laravel radionica',
        'description' => 'Opis radionice.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $event->categories()->attach($category);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/show')
            ->where('event.id', $event->id)
            ->where('event.title', 'Laravel radionica')
        );
});

test('korisnik moze pretrazivati dogadaje po nazivu', function () {
    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $laravelEvent = Event::create([
        'title' => 'Laravel radionica',
        'description' => 'Ucenje Laravela.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $laravelEvent->categories()->attach($category);

    $reactEvent = Event::create([
        'title' => 'React konferencija',
        'description' => 'Ucenje Reacta.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 50,
        'image' => null,
        'status' => 'published',
    ]);

    $reactEvent->categories()->attach($category);

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
        'title' => 'Laravel online radionica',
        'description' => 'Opis događaja.',
        'location' => 'Online',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $event1->categories()->attach($workshop);

    $event2 = Event::create([
        'title' => 'React konferencija',
        'description' => 'Opis događaja.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 50,
        'image' => null,
        'status' => 'published',
    ]);

    $event2->categories()->attach($conference);

    $event3 = Event::create([
        'title' => 'PHP radionica',
        'description' => 'Opis događaja.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(12),
        'ends_at' => null,
        'capacity' => 20,
        'image' => null,
        'status' => 'published',
    ]);

    $event3->categories()->attach($workshop);

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

    $laravelEvent = Event::create([
        'title' => 'Laravel radionica',
        'description' => 'Radionica o Laravelu.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'draft',
    ]);

    $laravelEvent->categories()->attach($category);

    $reactEvent = Event::create([
        'title' => 'React konferencija',
        'description' => 'Konferencija o Reactu.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 50,
        'image' => null,
        'status' => 'draft',
    ]);

    $reactEvent->categories()->attach($category);

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

test('dogadaj automatski dobija slug na osnovu naziva', function () {
    $event = Event::create([
        'title' => 'Laravel radionica u Zenici',
        'description' => 'Opis događaja.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'capacity' => 30,
        'status' => 'published',
    ]);

    expect($event->slug)->toBe('laravel-radionica-u-zenici');
});

test('dogadaji sa istim nazivom dobijaju razlicite slugove', function () {
    $firstEvent = Event::create([
        'title' => 'Laravel radionica',
        'description' => 'Prva radionica.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'capacity' => 30,
        'status' => 'published',
    ]);

    $secondEvent = Event::create([
        'title' => 'Laravel radionica',
        'description' => 'Druga radionica.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(10),
        'capacity' => 30,
        'status' => 'published',
    ]);

    expect($firstEvent->slug)->toBe('laravel-radionica')
        ->and($secondEvent->slug)->toBe('laravel-radionica-1');
});

test('slug dogadaja se mijenja kada se promijeni naziv', function () {
    $event = Event::create([
        'title' => 'Laravel radionica',
        'description' => 'Opis događaja.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'capacity' => 30,
        'status' => 'published',
    ]);

    $event->update([
        'title' => 'React radionica',
    ]);

    expect($event->fresh()->slug)->toBe('react-radionica');
});

test('detalji dogadaja dostupni su preko slug URL-a', function () {
    $event = Event::create([
        'title' => 'Laravel radionica',
        'description' => 'Opis događaja.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'capacity' => 30,
        'status' => 'published',
    ]);

    $this->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/show')
            ->where('event.id', $event->id)
            ->where('event.slug', 'laravel-radionica')
        );
});

test('arhiva prikazuje samo zavrsene dogadaje', function () {
    $expiredEvent = Event::create([
        'title' => 'Zavrsena Laravel radionica',
        'description' => 'Zavrsen dogadaj.',
        'location' => 'Zenica',
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->subDays(4),
        'capacity' => 30,
        'status' => 'expired',
    ]);

    Event::create([
        'title' => 'Buduca radionica',
        'description' => 'Buduci dogadaj.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(6),
        'capacity' => 30,
        'status' => 'published',
    ]);

    Event::create([
        'title' => 'Nacrt dogadaja',
        'description' => 'Neobjavljeni dogadaj.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(11),
        'capacity' => 30,
        'status' => 'draft',
    ]);

    $this->get(route('events.archive'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/archive')
            ->has('events.data', 1)
            ->where('events.data.0.id', $expiredEvent->id)
            ->where('events.data.0.title', 'Zavrsena Laravel radionica')
            ->where('events.total', 1)
        );
});

test('korisnik moze pretrazivati arhivu dogadaja', function () {
    $laravelEvent = Event::create([
        'title' => 'Laravel konferencija',
        'description' => 'Konferencija o Laravelu.',
        'location' => 'Zenica',
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->subDays(4),
        'capacity' => 30,
        'status' => 'expired',
    ]);

    Event::create([
        'title' => 'React konferencija',
        'description' => 'Konferencija o Reactu.',
        'location' => 'Sarajevo',
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDays(2),
        'capacity' => 30,
        'status' => 'expired',
    ]);

    $this->get(route('events.archive', [
        'search' => 'Laravel',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/archive')
            ->has('events.data', 1)
            ->where('events.data.0.id', $laravelEvent->id)
            ->where('events.total', 1)
        );
});

test('neprijavljeni korisnik moze vidjeti detalje zavrsenog dogadaja', function () {
    $event = Event::create([
        'title' => 'Zavrsena Laravel radionica',
        'description' => 'Detalji zavrsene radionice.',
        'location' => 'Zenica',
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->subDays(4),
        'capacity' => 30,
        'status' => 'expired',
    ]);

    $this->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/show')
            ->where('event.id', $event->id)
            ->where('event.slug', $event->slug)
            ->where('is_archived', true)
            ->where('can_register', false)
        );
});

test('zavrseni dogadaj nije dostupan za prijavu', function () {
    $user = User::factory()->create();

    $event = Event::create([
        'title' => 'Zavrsena radionica',
        'description' => 'Radionica koja je zavrsena.',
        'location' => 'Zenica',
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->subDays(4),
        'capacity' => 30,
        'status' => 'expired',
    ]);

    $this->actingAs($user)
        ->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('is_archived', true)
            ->where('can_register', false)
        );
});

test('buduci objavljeni dogadaj nije oznacen kao arhiviran', function () {
    $event = Event::create([
        'title' => 'Buduca Laravel radionica',
        'description' => 'Radionica koja tek dolazi.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(6),
        'capacity' => 30,
        'status' => 'published',
    ]);

    $this->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/show')
            ->where('event.id', $event->id)
            ->where('is_archived', false)
        );
});

test('detalji dogadaja sadrze maksimalan broj karata i grupe cijena sjedista', function () {
    $event = Event::create([
        'title' => 'Laravel konferencija',
        'description' => 'Opis konferencije.',
        'location' => 'Sarajevo',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'seat_rows' => 5,
        'seat_columns' => 6,
        'max_tickets_per_user' => 4,
        'image' => null,
        'status' => 'published',
    ]);

    $event->seatPriceGroups()->createMany([
        [
            'from_row' => 1,
            'to_row' => 2,
            'price' => 30,
        ],
        [
            'from_row' => 3,
            'to_row' => 5,
            'price' => 20,
        ],
    ]);

    $this->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/show')
            ->where('event.max_tickets_per_user', 4)
            ->has('event.seat_price_groups', 2)
            ->where('event.seat_price_groups.0.from_row', 1)
            ->where('event.seat_price_groups.0.to_row', 2)
            ->where('event.seat_price_groups.0.price', '30.00')
            ->where('event.seat_price_groups.1.from_row', 3)
            ->where('event.seat_price_groups.1.to_row', 5)
            ->where('event.seat_price_groups.1.price', '20.00')
        );
});
