<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin moze vidjeti listu korisnika', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    User::factory()->count(3)->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.users.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users', 4)
        );
});

test('regularni korisnik ne moze vidjeti listu korisnika', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $response = $this->actingAs($user)
        ->get(route('admin.users.index'));

    $response->assertForbidden();
});

test('neprijavljeni korisnik ne moze vidjeti listu korisnika', function () {
    $response = $this->get(route('admin.users.index'));

    $response->assertRedirect(route('login'));
});

test('admin moze urediti podatke korisnika', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($admin)->put(
        route('admin.users.update', $user),
        [
            'name' => 'Novo Ime',
            'email' => 'novo@example.com',
            'role' => 'admin',
        ]
    );

    $response->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Novo Ime',
        'email' => 'novo@example.com',
        'role' => 'admin',
    ]);
});

test('nije moguce dodijeliti email koji vec koristi drugi korisnik', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);
    $anotherUser = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($admin)->put(
        route('admin.users.update', $user),
        [
            'name' => $user->name,
            'email' => $anotherUser->email,
            'role' => 'user',
        ]
    );

    $response->assertSessionHasErrors('email');
});

test('admin ne moze obrisati vlastiti racun', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin));

    $response->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'deleted_at' => null,
    ]);
});

test('nije moguce obrisati posljednjeg administratora', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $anotherAdmin = User::factory()->create(['role' => 'admin']);

    $anotherAdmin->delete();

    $response = $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin));

    $response->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'deleted_at' => null,
    ]);
});

test('brisanje korisnika koristi soft delete', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user));

    $response->assertRedirect(route('admin.users.index'));

    $this->assertSoftDeleted('users', [
        'id' => $user->id,
    ]);
});
