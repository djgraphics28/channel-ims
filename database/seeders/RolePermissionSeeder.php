<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Permissions
        $permissions = [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            'units.view',
            'units.create',
            'units.edit',
            'units.delete',

            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',

            'branches.view',
            'branches.create',
            'branches.edit',
            'branches.delete',

            'brands.view',
            'brands.create',
            'brands.edit',
            'brands.delete',

            'products.view',
            'products.create',
            'products.edit',
            'products.delete',

            'quotations.view',
            'quotations.create',
            'quotations.edit',
            'quotations.delete',

            'dashboard.view',
            'settings.view',
            'cashflow.view',
            'reports.view',

            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',

            'cashflow.view',
            'cashflow.create',
            'cashflow.edit',
            'cashflow.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Roles and Assign Permissions
        $admin = Role::firstOrCreate(['name' => 'superadmin']);
        $admin->givePermissionTo($permissions);

        // Assign admin role to user with ID 1
        $user = \App\Models\User::find(1);
        $user->assignRole('superadmin');
    }
}
