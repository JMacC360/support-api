<?php

use App\Models\User;

it('can register a new user', function () {
    $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertCreated()
        ->assertJsonStructure(['user', 'access_token', 'token_type'])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.name', 'John Doe')
        ->assertJsonPath('user.email', 'john@example.com');

    $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
});

it('cannot register with an existing email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/register', [
        'name' => 'Duplicate',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('validates required fields on registration', function () {
    $this->postJson('/api/register', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('validates password confirmation on registration', function () {
    $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'wrong_confirmation',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('validates minimum password length on registration', function () {
    $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('can login with valid credentials', function () {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'password',
    ])
        ->assertSuccessful()
        ->assertJsonStructure(['user', 'access_token', 'token_type'])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', 'john@example.com');
});

it('cannot login with invalid credentials', function () {
    User::factory()->create(['email' => 'john@example.com']);

    $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'wrong_password',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid credentials');
});

it('validates required fields on login', function () {
    $this->postJson('/api/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('validates email format on login', function () {
    $this->postJson('/api/login', [
        'email' => 'not-an-email',
        'password' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('can fetch the authenticated user', function () {
    $user = User::factory()->create(['email' => 'john@example.com']);

    $this->actingAs($user)
        ->getJson('/api/me')
        ->assertSuccessful()
        ->assertJsonPath('email', 'john@example.com');
});

it('cannot fetch user when unauthenticated', function () {
    $this->getJson('/api/me')
        ->assertUnauthorized();
});

it('does not expose password or remember_token on user responses', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/me')
        ->assertSuccessful()
        ->assertJsonMissingPath('password')
        ->assertJsonMissingPath('remember_token');
});

it('can logout and invalidate the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/logout')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Logged out successfully');

    // Token should be removed from database
    expect($user->tokens()->count())->toBe(0);
});

it('cannot logout when unauthenticated', function () {
    $this->postJson('/api/logout')
        ->assertUnauthorized();
});

it('can register and then login with the same credentials', function () {
    $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secretpass',
        'password_confirmation' => 'secretpass',
    ])->assertCreated();

    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'secretpass',
    ])
        ->assertSuccessful()
        ->assertJsonStructure(['user', 'access_token', 'token_type']);
});
