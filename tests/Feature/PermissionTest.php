<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can list all permissions', function () {
    Permission::create(['name' => 'user.view', 'guard_name' => 'api']);
    Permission::create(['name' => 'user.create', 'guard_name' => 'api']);

    $this->getJson('/api/permissions')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'guard_name', 'created_at']]]);
});

it('can create a permission', function () {
    $this->postJson('/api/permissions', ['name' => 'user.view', 'guard_name' => 'api'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'user.view');

    $this->assertDatabaseHas('permissions', ['name' => 'user.view']);
});

it('cannot create a duplicate permission', function () {
    Permission::create(['name' => 'user.view', 'guard_name' => 'api']);

    $this->postJson('/api/permissions', ['name' => 'user.view'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('can show a permission', function () {
    $permission = Permission::create(['name' => 'user.view', 'guard_name' => 'api']);

    $this->getJson("/api/permissions/{$permission->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'user.view');
});

it('returns 404 for missing permission', function () {
    $this->getJson('/api/permissions/999')
        ->assertNotFound();
});

it('can update a permission', function () {
    $permission = Permission::create(['name' => 'user.view', 'guard_name' => 'api']);

    $this->putJson("/api/permissions/{$permission->id}", ['name' => 'user.read'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'user.read');

    $this->assertDatabaseHas('permissions', ['name' => 'user.read']);
});

it('can delete a permission', function () {
    $permission = Permission::create(['name' => 'user.view', 'guard_name' => 'api']);

    $this->deleteJson("/api/permissions/{$permission->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Permission deleted successfully');

    $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
});

it('requires authentication for permission endpoints', function () {
    auth()->forgetGuards();

    $this->getJson('/api/permissions')->assertUnauthorized();
    $this->postJson('/api/permissions', [])->assertUnauthorized();
});
