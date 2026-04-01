<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
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
        ]);

        // support-agent: read-only user access
        $agent = Role::firstOrCreate(['name' => 'support-agent', 'guard_name' => 'api']);
        $agent->syncPermissions([
            'user.view',
        ]);

        // Seed a default super-admin user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user->assignRole('super-admin');
    }
}
