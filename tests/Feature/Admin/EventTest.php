<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin moze vidjeti listu dogadaja', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $category = Category::create(['name' => 'Radionica']);

    $event = Event::create([
        'title' => 'Testni događaj',
        'description' => 'Opis testnog događaja.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'draft',
    ]);

    $event->categories()->attach($category->id);

    $this->actingAs($admin)
        ->get(route('admin.events.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/events/index')
            ->has('events.data', 1)
            ->where('events.data.0.title', 'Testni događaj')
            ->where('events.total', 1)
        );
});

test('obicni korisnik ne moze pristupiti dogadajima u administraciji', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)
        ->get(route('admin.events.index'))
        ->assertForbidden();
});

test('admin moze kreirati dogadaj', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Konferencija']);

    $response = $this->actingAs($admin)
        ->post(route('admin.events.store'), [
            'categories' => [$category->id],
            'title' => 'Laravel konferencija',
            'description' => 'Konferencija o Laravelu.',
            'location' => 'Sarajevo',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'capacity' => 50,
            'seat_rows' => 5,
            'seat_columns' => 10,
            'max_tickets_per_user' => 4,
            'price_groups' => [
                ['from_row' => 1, 'to_row' => 2, 'price' => 30],
                ['from_row' => 3, 'to_row' => 5, 'price' => 20],
            ],
            'status' => 'published',
        ]);

    $response->assertRedirect(route('admin.events.index'));

    $event = Event::where('title', 'Laravel konferencija')->firstOrFail();

    $this->assertDatabaseHas('category_event', [
        'event_id' => $event->id,
        'category_id' => $category->id,
    ]);

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'title' => 'Laravel konferencija',
        'location' => 'Sarajevo',
        'capacity' => 50,
        'seat_rows' => 5,
        'seat_columns' => 10,
        'max_tickets_per_user' => 4,
        'status' => 'published',
    ]);

    $this->assertDatabaseHas('event_seat_price_groups', [
        'event_id' => $event->id,
        'from_row' => 1,
        'to_row' => 2,
        'price' => 30,
    ]);

    $this->assertDatabaseHas('event_seat_price_groups', [
        'event_id' => $event->id,
        'from_row' => 3,
        'to_row' => 5,
        'price' => 20,
    ]);
});

test('admin moze urediti dogadaj', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Radionica']);

    $event = Event::create([
        'title' => 'Stari naziv',
        'description' => 'Stari opis.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'draft',
    ]);

    $event->categories()->attach($category->id);

    $response = $this->actingAs($admin)
        ->put(route('admin.events.update', $event), [
            'categories' => [$category->id],
            'title' => 'Novi naziv',
            'description' => 'Novi opis.',
            'location' => 'Sarajevo',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'capacity' => 50,
            'seat_rows' => 5,
            'seat_columns' => 10,
            'max_tickets_per_user' => 5,
            'price_groups' => [
                ['from_row' => 1, 'to_row' => 5, 'price' => 25],
            ],
            'status' => 'published',
        ]);

    $response->assertRedirect(route('admin.events.index'));

    $this->assertDatabaseHas('category_event', [
        'event_id' => $event->id,
        'category_id' => $category->id,
    ]);

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'title' => 'Novi naziv',
        'description' => 'Novi opis.',
        'location' => 'Sarajevo',
        'capacity' => 50,
        'seat_rows' => 5,
        'seat_columns' => 10,
        'max_tickets_per_user' => 5,
        'status' => 'published',
    ]);

    $this->assertDatabaseHas('event_seat_price_groups', [
        'event_id' => $event->id,
        'from_row' => 1,
        'to_row' => 5,
        'price' => 25,
    ]);
});

test('admin ne moze kreirati dogadaj sa kapacitetom koji ne odgovara dimenzijama sjedista', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Konferencija']);

    $this->actingAs($admin)
        ->post(route('admin.events.store'), [
            'categories' => [$category->id],
            'title' => 'Laravel konferencija',
            'description' => 'Konferencija o Laravelu.',
            'location' => 'Sarajevo',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'capacity' => 40,
            'seat_rows' => 5,
            'seat_columns' => 10,
            'max_tickets_per_user' => 4,
            'price_groups' => [
                ['from_row' => 1, 'to_row' => 5, 'price' => 20],
            ],
            'status' => 'published',
        ])
        ->assertSessionHasErrors(['capacity']);

    $this->assertDatabaseCount('events', 0);
});

test('admin moze obrisati dogadaj', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Radionica']);

    $event = Event::create([
        'title' => 'Događaj za brisanje',
        'description' => 'Ovaj događaj će biti obrisan.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'draft',
    ]);

    $event->categories()->attach($category->id);

    $this->actingAs($admin)
        ->delete(route('admin.events.destroy', $event))
        ->assertRedirect(route('admin.events.index'));

    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

test('admin ne moze kreirati dogadaj sa neispravnim podacima', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.events.store'), [
            'categories' => [],
            'title' => '',
            'description' => '',
            'location' => '',
            'starts_at' => '',
            'ends_at' => null,
            'capacity' => 0,
            'status' => 'neispravan',
        ])
        ->assertSessionHasErrors([
            'categories',
            'title',
            'description',
            'location',
            'starts_at',
            'capacity',
            'seat_rows',
            'seat_columns',
            'max_tickets_per_user',
            'price_groups',
            'status',
        ]);

    $this->assertDatabaseCount('events', 0);
});

test('admin ne moze kreirati dogadaj gdje je datum zavrsetka prije datuma pocetka', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Seminar']);

    $this->actingAs($admin)
        ->post(route('admin.events.store'), [
            'categories' => [$category->id],
            'title' => 'Testni seminar',
            'description' => 'Opis seminara.',
            'location' => 'Zenica',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(9)->format('Y-m-d H:i:s'),
            'capacity' => 20,
            'seat_rows' => 4,
            'seat_columns' => 5,
            'max_tickets_per_user' => 2,
            'price_groups' => [
                ['from_row' => 1, 'to_row' => 4, 'price' => 20],
            ],
            'status' => 'draft',
        ])
        ->assertSessionHasErrors(['ends_at']);

    $this->assertDatabaseCount('events', 0);
});

test('admin ne moze kreirati dogadaj sa neispravnom slikom', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Radionica']);

    $this->actingAs($admin)
        ->post(route('admin.events.store'), [
            'categories' => [$category->id],
            'title' => 'Testni događaj',
            'description' => 'Opis događaja.',
            'location' => 'Zenica',
            'starts_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'capacity' => 30,
            'seat_rows' => 5,
            'seat_columns' => 6,
            'max_tickets_per_user' => 2,
            'price_groups' => [
                ['from_row' => 1, 'to_row' => 5, 'price' => 20],
            ],
            'status' => 'draft',
            'image' => 'ovo-nije-slika',
        ])
        ->assertSessionHasErrors(['image']);

    $this->assertDatabaseCount('events', 0);
});

test('admin moze kreirati dogadaj sa vise tagova', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Radionica']);
    $tag1 = Tag::create(['name' => 'Besplatno']);
    $tag2 = Tag::create(['name' => 'Online']);

    $response = $this->actingAs($admin)
        ->post(route('admin.events.store'), [
            'categories' => [$category->id],
            'title' => 'Laravel radionica',
            'description' => 'Radionica za pocetnike.',
            'location' => 'Online',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'capacity' => 30,
            'seat_rows' => 5,
            'seat_columns' => 6,
            'max_tickets_per_user' => 3,
            'price_groups' => [
                ['from_row' => 1, 'to_row' => 5, 'price' => 15],
            ],
            'status' => 'published',
            'tags' => [$tag1->id, $tag2->id],
        ]);

    $response->assertRedirect(route('admin.events.index'));

    $event = Event::where('title', 'Laravel radionica')->firstOrFail();

    $this->assertDatabaseHas('category_event', [
        'event_id' => $event->id,
        'category_id' => $category->id,
    ]);

    $this->assertDatabaseHas('event_tag', [
        'event_id' => $event->id,
        'tag_id' => $tag1->id,
    ]);

    $this->assertDatabaseHas('event_tag', [
        'event_id' => $event->id,
        'tag_id' => $tag2->id,
    ]);

    expect($event->tags)->toHaveCount(2);
});

test('admin moze urediti tagove dogadaja', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Radionica']);
    $tag1 = Tag::create(['name' => 'Besplatno']);
    $tag2 = Tag::create(['name' => 'Online']);
    $tag3 = Tag::create(['name' => 'Za pocetnike']);

    $event = Event::create([
        'title' => 'Laravel radionica',
        'description' => 'Radionica za pocetnike.',
        'location' => 'Online',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $event->categories()->attach($category->id);
    $event->tags()->sync([$tag1->id, $tag2->id]);

    $response = $this->actingAs($admin)
        ->put(route('admin.events.update', $event), [
            'categories' => [$category->id],
            'title' => 'Laravel radionica',
            'description' => 'Radionica za pocetnike.',
            'location' => 'Online',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'capacity' => 30,
            'seat_rows' => 5,
            'seat_columns' => 6,
            'max_tickets_per_user' => 3,
            'price_groups' => [
                ['from_row' => 1, 'to_row' => 5, 'price' => 15],
            ],
            'status' => 'published',
            'tags' => [$tag3->id],
        ]);

    $response->assertRedirect(route('admin.events.index'));

    $this->assertDatabaseHas('event_tag', [
        'event_id' => $event->id,
        'tag_id' => $tag3->id,
    ]);

    $this->assertDatabaseMissing('event_tag', [
        'event_id' => $event->id,
        'tag_id' => $tag1->id,
    ]);

    $this->assertDatabaseMissing('event_tag', [
        'event_id' => $event->id,
        'tag_id' => $tag2->id,
    ]);

    expect($event->fresh()->tags)->toHaveCount(1);
    expect($event->fresh()->tags->first()->id)->toBe($tag3->id);
});

test('admin moze sakriti objavljeni dogadaj', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Radionica']);

    $event = Event::create([
        'title' => 'Objavljena radionica',
        'description' => 'Opis radionice.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $event->categories()->attach($category->id);

    $response = $this->actingAs($admin)
        ->put(route('admin.events.update', $event), [
            'categories' => [$category->id],
            'title' => 'Objavljena radionica',
            'description' => 'Opis radionice.',
            'location' => 'Zenica',
            'starts_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'capacity' => 30,
            'seat_rows' => 5,
            'seat_columns' => 6,
            'max_tickets_per_user' => 2,
            'price_groups' => [
                ['from_row' => 1, 'to_row' => 5, 'price' => 20],
            ],
            'status' => 'draft',
        ]);

    $response->assertRedirect(route('admin.events.index'));

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'status' => 'draft',
    ]);
});

test('admin moze duplicirati dogadaj kao nacrt', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Radionica']);
    $tag = Tag::create(['name' => 'Besplatno']);

    $event = Event::create([
        'title' => 'Laravel radionica',
        'description' => 'Opis radionice.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => now()->addDays(8),
        'capacity' => 30,
        'seat_rows' => 3,
        'seat_columns' => 10,
        'max_tickets_per_user' => 4,
        'image' => null,
        'status' => 'published',
    ]);

    $event->categories()->attach($category->id);
    $event->tags()->attach($tag->id);
    $event->seatPriceGroups()->create([
        'from_row' => 1,
        'to_row' => 1,
        'price' => 30,
    ]);
    $event->seatPriceGroups()->create([
        'from_row' => 2,
        'to_row' => 3,
        'price' => 20,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.events.duplicate', $event));

    $copy = Event::where('title', 'Laravel radionica (kopija)')
        ->firstOrFail();

    $response->assertRedirect(route('admin.events.edit', $copy));

    $this->assertDatabaseHas('events', [
        'id' => $copy->id,
        'title' => 'Laravel radionica (kopija)',
        'description' => 'Opis radionice.',
        'location' => 'Zenica',
        'capacity' => 30,
        'seat_rows' => 3,
        'seat_columns' => 10,
        'max_tickets_per_user' => 4,
        'status' => 'draft',
    ]);

    expect($copy->id)->not->toBe($event->id);

    $this->assertDatabaseHas('event_seat_price_groups', [
        'event_id' => $copy->id,
        'from_row' => 1,
        'to_row' => 1,
        'price' => 30,
    ]);

    $this->assertDatabaseHas('event_seat_price_groups', [
        'event_id' => $copy->id,
        'from_row' => 2,
        'to_row' => 3,
        'price' => 20,
    ]);
});

test('duplicirani dogadaj zadrzava kategorije tagove i cijene sjedista', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $category1 = Category::create(['name' => 'Radionica']);
    $category2 = Category::create(['name' => 'Edukacija']);
    $tag1 = Tag::create(['name' => 'Besplatno']);
    $tag2 = Tag::create(['name' => 'Online']);

    $event = Event::create([
        'title' => 'Laravel radionica',
        'description' => 'Opis radionice.',
        'location' => 'Online',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'seat_rows' => 5,
        'seat_columns' => 6,
        'max_tickets_per_user' => 3,
        'image' => null,
        'status' => 'published',
    ]);

    $event->categories()->sync([$category1->id, $category2->id]);
    $event->tags()->sync([$tag1->id, $tag2->id]);

    $event->seatPriceGroups()->create([
        'from_row' => 1,
        'to_row' => 2,
        'price' => 25,
    ]);

    $event->seatPriceGroups()->create([
        'from_row' => 3,
        'to_row' => 5,
        'price' => 15,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.events.duplicate', $event))
        ->assertRedirect();

    $copy = Event::where('title', 'Laravel radionica (kopija)')
        ->firstOrFail();

    expect($copy->categories->modelKeys())
        ->toEqualCanonicalizing([$category1->id, $category2->id]);

    expect($copy->tags->modelKeys())
        ->toEqualCanonicalizing([$tag1->id, $tag2->id]);

    expect($copy->max_tickets_per_user)->toBe(3);

    expect($copy->seatPriceGroups)
        ->toHaveCount(2);

    expect($copy->seatPriceGroups->pluck('price')->all())
        ->toEqual(['25.00', '15.00']);
});

test('obicni korisnik ne moze duplicirati dogadaj', function () {
    $user = User::factory()->create(['role' => 'user']);

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

    $this->actingAs($user)
        ->post(route('admin.events.duplicate', $event))
        ->assertForbidden();

    $this->assertDatabaseCount('events', 1);
});
