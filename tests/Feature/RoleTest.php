<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can list all roles', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'api']);
    Role::create(['name' => 'editor', 'guard_name' => 'api']);

    $this->getJson('/api/roles')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'guard_name', 'permissions', 'created_at']]]);
});

it('can create a role', function () {
    $this->postJson('/api/roles', ['name' => 'manager', 'guard_name' => 'api'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'manager');

    $this->assertDatabaseHas('roles', ['name' => 'manager']);
});

it('can create a role with permissions', function () {
    $permission = Permission::create(['name' => 'user.view', 'guard_name' => 'api']);

    $this->postJson('/api/roles', [
        'name' => 'viewer',
        'permissions' => ['user.view'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'viewer')
        ->assertJsonCount(1, 'data.permissions');
});

it('cannot create a duplicate role', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'api']);

    $this->postJson('/api/roles', ['name' => 'admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('can show a role', function () {
    $role = Role::create(['name' => 'admin', 'guard_name' => 'api']);

    $this->getJson("/api/roles/{$role->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'admin');
});

it('returns 404 for missing role', function () {
    $this->getJson('/api/roles/999')
        ->assertNotFound();
});

it('can update a role', function () {
    $role = Role::create(['name' => 'admin', 'guard_name' => 'api']);

    $this->putJson("/api/roles/{$role->id}", ['name' => 'super-admin'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'super-admin');
});

it('can update a role and sync permissions', function () {
    $permission = Permission::create(['name' => 'user.delete', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'admin', 'guard_name' => 'api']);

    $this->putJson("/api/roles/{$role->id}", [
        'name' => 'admin',
        'permissions' => ['user.delete'],
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.permissions');
});

it('can delete a role', function () {
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

    $this->deleteJson("/api/roles/{$role->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Role deleted successfully');

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

it('can list permissions for a role', function () {
    $permission = Permission::create(['name' => 'user.view', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
    $role->givePermissionTo('user.view');

    $this->getJson("/api/roles/{$role->id}/permissions")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'user.view');
});

it('requires authentication for role endpoints', function () {
    auth()->forgetGuards();

    $this->getJson('/api/roles')->assertUnauthorized();
    $this->postJson('/api/roles', [])->assertUnauthorized();
});
