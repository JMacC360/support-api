<?php

use App\Models\Category;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('seeds the default ticket categories', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Category::query()->pluck('name')->sort()->values()->all())->toBe([
        'Access',
        'Account',
        'Billing',
        'General',
        'Technical',
    ]);
});

it('lists seeded categories for authorized users', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'category.view', 'guard_name' => 'api']);
    $user->givePermissionTo('category.view');

    $this->actingAs($user)
        ->getJson('/api/categories')
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'Technical'])
        ->assertJsonFragment(['name' => 'Billing'])
        ->assertJsonFragment(['name' => 'Access'])
        ->assertJsonFragment(['name' => 'Account'])
        ->assertJsonFragment(['name' => 'General']);
});
