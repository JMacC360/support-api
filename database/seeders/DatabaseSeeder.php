<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Define all permissions
        $permissions = [
            // Role management
            'role.view',
            'role.create',
            'role.update',
            'role.delete',

            // Permission management
            'permission.view',
            'permission.create',
            'permission.update',
            'permission.delete',

            // User management
            'user.view',
            'user.create',
            'user.update',
            'user.delete',
            'user.assign-role',

            // Ticket management
            'ticket.view',
            'ticket.create',
            'ticket.update',
            'ticket.status.update',
            'ticket.delete',

            // Ticket threads (replies / internal notes)
            'ticket.thread.create',
            'ticket.thread.update',
            'ticket.thread.delete',
            'ticket.thread.internal.view',
            'ticket.thread.internal.create',

            // Categories (ticket metadata)
            'category.view',
            'category.create',
            'category.update',
            'category.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // super-admin: all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'api']);
        $superAdmin->syncPermissions($permissions);

        // admin: everything except delete and role/permission management
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $admin->syncPermissions([
            'role.view',
            'permission.view',
            'user.view',
            'user.create',
            'user.update',
            'user.assign-role',

            'ticket.view',
            'ticket.create',
            'ticket.update',
            'ticket.status.update',
            'ticket.thread.create',
            'ticket.thread.update',
            'ticket.thread.internal.view',
            'ticket.thread.internal.create',

            'category.view',
            'category.create',
            'category.update',
        ]);

        // support-agent: read-only user access
        $agent = Role::firstOrCreate(['name' => 'support-agent', 'guard_name' => 'api']);
        $agent->syncPermissions([
            'user.view',

            'ticket.view',
            'ticket.create',
            'ticket.thread.create',

            'category.view',
        ]);

        // Seed a default super-admin user
        $user = User::query()->where('email', 'test@example.com')->first();
        if (! $user) {
            $user = User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        if (! $user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }

        $defaultCategories = [
            ['name' => 'Technical', 'description' => 'Product, platform, and integration issues'],
            ['name' => 'Billing', 'description' => 'Invoices, payments, and subscription billing'],
            ['name' => 'Access', 'description' => 'Login, permissions, and account access'],
            ['name' => 'Account', 'description' => 'Profile, organization, and account settings'],
            ['name' => 'General', 'description' => 'General support requests'],
        ];

        foreach ($defaultCategories as $category) {
            Category::query()->firstOrCreate(
                ['name' => $category['name']],
                ['description' => $category['description']]
            );
        }
    }
}
