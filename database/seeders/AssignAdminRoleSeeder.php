<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AssignAdminRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // التأكد من وجود دور المسؤول
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        
        // تعيين المستخدم الأول كمسؤول
        $admin = User::where('email', 'admin@admin.com')->first();
        if ($admin) {
            $admin->assignRole('admin');
            $this->command->info('تم تعيين المستخدم admin@admin.com كمسؤول');
        }
        
        // يمكنك إضافة مستخدمين آخرين كمسؤولين هنا إذا لزم الأمر
    }
}