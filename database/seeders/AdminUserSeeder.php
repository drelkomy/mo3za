<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate([
            'email' => 'admin@admin.com',
        ], [
            'name' => 'Admin',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
        ]);
        $admin->assignRole('admin');
    }
}
