<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actor = User::factory()->create();
    $this->actingAs($this->actor);

    Permission::firstOrCreate(['name' => 'user.view', 'guard_name' => 'api']);
    Permission::firstOrCreate(['name' => 'user.create', 'guard_name' => 'api']);
    Permission::firstOrCreate(['name' => 'user.update', 'guard_name' => 'api']);
    Permission::firstOrCreate(['name' => 'user.delete', 'guard_name' => 'api']);

    $this->actor->givePermissionTo([
        'user.view',
        'user.create',
        'user.update',
        'user.delete',
    ]);
});

it('can list users', function () {
    User::factory()->count(2)->create();

    $this->getJson('/api/users')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [[
                'id',
                'name',
                'email',
                'is_active',
                'roles',
                'role_names',
                'created_at',
                'updated_at',
            ]],
        ]);
});

it('can create a new user with roles', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'api']);

    $this->postJson('/api/users', [
        'name' => 'Managed User',
        'email' => 'managed@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['admin'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'managed@example.com')
        ->assertJsonPath('data.role_names.0', 'admin');

    $this->assertDatabaseHas('users', ['email' => 'managed@example.com']);
});

it('can show a user', function () {
    $user = User::factory()->create();

    $this->getJson("/api/users/{$user->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id);
});

it('can update a user details and roles', function () {
    $user = User::factory()->create();
    Role::create(['name' => 'support-agent', 'guard_name' => 'api']);

    $this->putJson("/api/users/{$user->id}", [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'roles' => ['support-agent'],
        'is_active' => false,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.email', 'updated@example.com')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.role_names.0', 'support-agent');
});

it('can toggle user active status', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->patchJson("/api/users/{$user->id}/status", [
        'is_active' => false,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.is_active', false);
});

it('can delete a user', function () {
    $user = User::factory()->create();

    $this->deleteJson("/api/users/{$user->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'User deleted successfully');

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('validates required fields for creating a user', function () {
    $this->postJson('/api/users', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('requires authentication for user management endpoints', function () {
    auth()->forgetGuards();

    $this->getJson('/api/users')->assertUnauthorized();
    $this->postJson('/api/users', [])->assertUnauthorized();
});
