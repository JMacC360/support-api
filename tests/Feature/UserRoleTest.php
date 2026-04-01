<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actor = User::factory()->create();
    $this->actingAs($this->actor);

    $this->targetUser = User::factory()->create();
});

it('can list roles for a user', function () {
    $role = Role::create(['name' => 'admin', 'guard_name' => 'api']);
    $this->targetUser->assignRole($role);

    $this->getJson("/api/users/{$this->targetUser->id}/roles")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'admin');
});

it('can sync roles for a user', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'api']);
    Role::create(['name' => 'editor', 'guard_name' => 'api']);

    $this->targetUser->assignRole('admin');

    $this->putJson("/api/users/{$this->targetUser->id}/roles", ['roles' => ['editor']])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'editor');

    expect($this->targetUser->fresh()->hasRole('admin'))->toBeFalse();
    expect($this->targetUser->fresh()->hasRole('editor'))->toBeTrue();
});

it('validates roles exist when syncing', function () {
    $this->putJson("/api/users/{$this->targetUser->id}/roles", ['roles' => ['nonexistent-role']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['roles.0']);
});

it('can list all permissions for a user', function () {
    $permission = Permission::create(['name' => 'user.view', 'guard_name' => 'api']);
    $role = Role::create(['name' => 'viewer', 'guard_name' => 'api']);
    $role->givePermissionTo('user.view');
    $this->targetUser->assignRole('viewer');

    $this->getJson("/api/users/{$this->targetUser->id}/permissions")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'user.view');
});

it('can sync direct permissions for a user', function () {
    Permission::create(['name' => 'user.view', 'guard_name' => 'api']);
    Permission::create(['name' => 'user.create', 'guard_name' => 'api']);

    $this->targetUser->givePermissionTo('user.view');

    $this->putJson("/api/users/{$this->targetUser->id}/permissions", ['permissions' => ['user.create']])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'user.create');
});

it('validates permissions exist when syncing', function () {
    $this->putJson("/api/users/{$this->targetUser->id}/permissions", ['permissions' => ['nonexistent.permission']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions.0']);
});

it('returns 404 for a missing user', function () {
    $this->getJson('/api/users/999/roles')->assertNotFound();
    $this->getJson('/api/users/999/permissions')->assertNotFound();
});

it('requires authentication for user role endpoints', function () {
    auth()->forgetGuards();

    $this->getJson("/api/users/{$this->targetUser->id}/roles")->assertUnauthorized();
    $this->putJson("/api/users/{$this->targetUser->id}/roles", [])->assertUnauthorized();
});
