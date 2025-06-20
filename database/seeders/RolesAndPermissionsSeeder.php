<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // تعريف الأدوار
        $roles = [
            'admin',
            'member',
        ];

        // تعريف الصلاحيات
        $permissions = [
            'manage users',
            'manage tasks',
            'manage rewards',
            'manage subscriptions',
            'view subscriptions',
            'edit subscriptions',
            'manage payments',
            'view payments',
            'edit payments',
            'manage packages',
            'edit packages',
            'manage stages',
            'view reports',
            'upload proof',
            'approve stage',
            'reject stage',
        ];

        // إنشاء الصلاحيات
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // إنشاء الأدوار وإسناد الصلاحيات
        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            
            if ($roleName === 'admin') {
                // المسؤول لديه جميع الصلاحيات
                $role->syncPermissions($permissions);
            } else if ($roleName === 'member') {
                // العضو لديه صلاحيات محددة فقط
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