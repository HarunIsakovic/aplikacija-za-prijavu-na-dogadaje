<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin moze vidjeti kategorije', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.categories.index'));

    $response->assertOk();
});

test('regularni korisnik ne moze vidjeti kategorije', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $response = $this->actingAs($user)
        ->get(route('admin.categories.index'));

    $response->assertForbidden();
});

test('admin moze dodati kategoriju', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.categories.store'), [
            'name' => 'Testna kategorija',
        ]);

    $response->assertRedirect(route('admin.categories.index'));

    $this->assertDatabaseHas('categories', [
        'name' => 'Testna kategorija',
    ]);
});

test('admin ne moze dodati kategoriju sa postojecim nazivom', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    Category::create([
        'name' => 'Radionica',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.categories.store'), [
            'name' => 'Radionica',
        ]);

    $response->assertSessionHasErrors('name');

    $this->assertDatabaseCount('categories', 1);
});

test('admin moze urediti kategoriju', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $category = Category::create([
        'name' => 'Stari naziv',
    ]);

    $response = $this->actingAs($admin)
        ->put(route('admin.categories.update', $category), [
            'name' => 'Novi naziv',
        ]);

    $response->assertRedirect(route('admin.categories.index'));

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Novi naziv',
    ]);
});

test('admin ne moze urediti kategoriju u naziv koji vec postoji', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    Category::create([
        'name' => 'Radionica',
    ]);

    $category = Category::create([
        'name' => 'Konferencija',
    ]);

    $response = $this->actingAs($admin)
        ->put(route('admin.categories.update', $category), [
            'name' => 'Radionica',
        ]);

    $response->assertSessionHasErrors('name');

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Konferencija',
    ]);
});

test('admin moze zadrzati postojeci naziv kategorije', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $response = $this->actingAs($admin)
        ->put(route('admin.categories.update', $category), [
            'name' => 'Radionica',
        ]);

    $response->assertRedirect(route('admin.categories.index'));

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Radionica',
    ]);
});

test('admin moze obrisati kategoriju bez povezanih dogadjaja', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $category = Category::create([
        'name' => 'Kategorija za brisanje',
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.categories.destroy', $category));

    $response->assertRedirect(route('admin.categories.index'));

    $this->assertDatabaseMissing('categories', [
        'id' => $category->id,
    ]);
});

test('admin ne moze obrisati kategoriju koja ima povezane dogadjaje', function () {
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
        'capacity' => 20,
        'image' => null,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.categories.destroy', $category));

    $response->assertRedirect(route('admin.categories.index'));

    $response->assertSessionHas(
        'error',
        'Nije moguće obrisati kategoriju koja ima povezane događaje.'
    );

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Radionica',
    ]);
});

test('admin moze pretrazivati kategorije po nazivu', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    Category::create(['name' => 'Radionica']);
    Category::create(['name' => 'Konferencija']);

    $this->actingAs($admin)
        ->get(route('admin.categories.index', ['search' => 'Radionica']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Radionica')
            ->where('categories.total', 1)
        );
});