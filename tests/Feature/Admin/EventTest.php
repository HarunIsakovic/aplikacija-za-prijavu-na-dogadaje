<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin moze vidjeti listu dogadaja', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    Event::create([
        'category_id' => $category->id,
        'title' => 'Testni događaj',
        'description' => 'Opis testnog događaja.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.events.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/events/index')
            ->has('events', 1)
        );
});

test('obicni korisnik ne moze pristupiti dogadajima u administraciji', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $response = $this->actingAs($user)
        ->get(route('admin.events.index'));

    $response->assertForbidden();
});

test('admin moze kreirati dogadaj', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $category = Category::create([
        'name' => 'Konferencija',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.events.store'), [
            'category_id' => $category->id,
            'title' => 'Laravel konferencija',
            'description' => 'Konferencija o Laravelu.',
            'location' => 'Sarajevo',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'capacity' => 50,
            'status' => 'published',
        ]);

    $response->assertRedirect(route('admin.events.index'));

    $this->assertDatabaseHas('events', [
        'category_id' => $category->id,
        'title' => 'Laravel konferencija',
        'location' => 'Sarajevo',
        'capacity' => 50,
        'status' => 'published',
    ]);
});

test('admin moze urediti dogadaj', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $event = Event::create([
        'category_id' => $category->id,
        'title' => 'Stari naziv',
        'description' => 'Stari opis.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin)
        ->put(route('admin.events.update', $event), [
            'category_id' => $category->id,
            'title' => 'Novi naziv',
            'description' => 'Novi opis.',
            'location' => 'Sarajevo',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'capacity' => 50,
            'status' => 'published',
        ]);

    $response->assertRedirect(route('admin.events.index'));

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'title' => 'Novi naziv',
        'description' => 'Novi opis.',
        'location' => 'Sarajevo',
        'capacity' => 50,
        'status' => 'published',
    ]);
});

test('admin moze obrisati dogadaj', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $event = Event::create([
        'category_id' => $category->id,
        'title' => 'Događaj za brisanje',
        'description' => 'Ovaj događaj će biti obrisan.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.events.destroy', $event));

    $response->assertRedirect(route('admin.events.index'));

    $this->assertDatabaseMissing('events', [
        'id' => $event->id,
    ]);
});

test('admin ne moze kreirati dogadaj sa neispravnim podacima', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.events.store'), [
            'category_id' => 999,
            'title' => '',
            'description' => '',
            'location' => '',
            'starts_at' => '',
            'ends_at' => null,
            'capacity' => 0,
            'status' => 'neispravan',
        ]);

    $response->assertSessionHasErrors([
        'category_id',
        'title',
        'description',
        'location',
        'starts_at',
        'capacity',
        'status',
    ]);

    $this->assertDatabaseCount('events', 0);
});

test('admin ne moze kreirati dogadaj gdje je datum zavrsetka prije datuma pocetka', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $category = Category::create([
        'name' => 'Seminar',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.events.store'), [
            'category_id' => $category->id,
            'title' => 'Testni seminar',
            'description' => 'Opis seminara.',
            'location' => 'Zenica',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(9)->format('Y-m-d H:i:s'),
            'capacity' => 20,
            'status' => 'draft',
        ]);

    $response->assertSessionHasErrors(['ends_at']);

    $this->assertDatabaseCount('events', 0);
});

test('admin ne moze kreirati dogadaj sa neispravnom slikom', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.events.store'), [
            'category_id' => $category->id,
            'title' => 'Testni događaj',
            'description' => 'Opis događaja.',
            'location' => 'Zenica',
            'starts_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'capacity' => 30,
            'status' => 'draft',
            'image' => 'ovo-nije-slika',
        ]);

    $response->assertSessionHasErrors(['image']);

    $this->assertDatabaseCount('events', 0);
});

test('admin moze kreirati dogadaj sa vise tagova', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $tag1 = Tag::create([
        'name' => 'Besplatno',
    ]);

    $tag2 = Tag::create([
        'name' => 'Online',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.events.store'), [
            'category_id' => $category->id,
            'title' => 'Laravel radionica',
            'description' => 'Radionica za pocetnike.',
            'location' => 'Online',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'capacity' => 30,
            'status' => 'published',
            'tags' => [$tag1->id, $tag2->id],
        ]);

    $response->assertRedirect(route('admin.events.index'));

    $event = Event::where('title', 'Laravel radionica')->firstOrFail();

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
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $tag1 = Tag::create([
        'name' => 'Besplatno',
    ]);

    $tag2 = Tag::create([
        'name' => 'Online',
    ]);

    $tag3 = Tag::create([
        'name' => 'Za pocetnike',
    ]);

    $event = Event::create([
        'category_id' => $category->id,
        'title' => 'Laravel radionica',
        'description' => 'Radionica za pocetnike.',
        'location' => 'Online',
        'starts_at' => now()->addDays(10),
        'ends_at' => null,
        'capacity' => 30,
        'image' => null,
        'status' => 'published',
    ]);

    $event->tags()->sync([$tag1->id, $tag2->id]);

    $response = $this->actingAs($admin)
        ->put(route('admin.events.update', $event), [
            'category_id' => $category->id,
            'title' => 'Laravel radionica',
            'description' => 'Radionica za pocetnike.',
            'location' => 'Online',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'capacity' => 30,
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
