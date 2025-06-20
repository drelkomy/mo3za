<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestMembersSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            ['name' => 'أحمد محمد', 'email' => 'ahmed@test.com'],
            ['name' => 'فاطمة علي', 'email' => 'fatima@test.com'],
            ['name' => 'محمد سالم', 'email' => 'mohammed@test.com'],
            ['name' => 'نورا أحمد', 'email' => 'nora@test.com'],
            ['name' => 'خالد عبدالله', 'email' => 'khalid@test.com'],
        ];

        foreach ($members as $member) {
            $user = User::firstOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['name'],
                    'password' => Hash::make('password'),
                    'user_type' => 'member',
                    'is_active' => true,
                ]
            );
            $user->assignRole('member');
        }
    }
}