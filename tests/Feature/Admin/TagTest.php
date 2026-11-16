
<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin moze vidjeti listu tagova', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    Tag::create([
        'name' => 'Laravel',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tags.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/tags/index')
            ->has('tags.data', 1)
            ->where('tags.data.0.name', 'Laravel')
            ->where('tags.total', 1)
        );
});

test('obicni korisnik ne moze pristupiti tagovima u administraciji', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $this->actingAs($user)
        ->get(route('admin.tags.index'))
        ->assertForbidden();
});

test('admin moze kreirati tag', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tags.store'), [
            'name' => 'Laravel',
        ])
        ->assertRedirect(route('admin.tags.index'));

    $this->assertDatabaseHas('tags', [
        'name' => 'Laravel',
    ]);
});

test('admin moze urediti tag', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $tag = Tag::create([
        'name' => 'Laravel',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.tags.update', $tag), [
            'name' => 'React',
        ])
        ->assertRedirect(route('admin.tags.index'));

    $this->assertDatabaseHas('tags', [
        'id' => $tag->id,
        'name' => 'React',
    ]);
});

test('admin moze obrisati tag', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $tag = Tag::create([
        'name' => 'Laravel',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.tags.destroy', $tag))
        ->assertRedirect(route('admin.tags.index'));

    $this->assertDatabaseMissing('tags', [
        'id' => $tag->id,
    ]);
});

test('nije moguce kreirati dva taga sa istim nazivom', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    Tag::create([
        'name' => 'Laravel',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tags.store'), [
            'name' => 'Laravel',
        ])
        ->assertSessionHasErrors('name');

    expect(Tag::where('name', 'Laravel')->count())->toBe(1);
});

test('admin moze pretrazivati tagove po nazivu', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    Tag::create(['name' => 'Laravel']);
    Tag::create(['name' => 'React']);

    $this->actingAs($admin)
        ->get(route('admin.tags.index', ['search' => 'Laravel']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tags.data', 1)
            ->where('tags.data.0.name', 'Laravel')
            ->where('tags.total', 1)
        );
});
