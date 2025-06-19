<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Define roles
        $roles = [
            'admin',
            'member',
        ];

        // Define permissions
        $permissions = [
            'manage users',
            'manage tasks',
            'manage rewards',
            'manage subscriptions',
            'manage packages',
            'manage stages',
            'view reports',
            'upload proof',
            'approve stage',
            'reject stage',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            if ($roleName === 'admin') {
                $role->syncPermissions($permissions);
            } else if ($roleName === 'member') {
                $role->syncPermissions([
                    'manage tasks',
                    'view reports',
                    'upload proof',
                    'approve stage',
                    'reject stage',
                ]);
            }
        }
    }
}
