<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class TrialPackageSeeder extends Seeder
{
    public function run(): void
    {
        Package::firstOrCreate(
            ['name' => 'الباقة التجريبية'],
            [
                'price' => 0,
                'max_tasks' => 3,
                'max_participants' => 2,
                'max_milestones_per_task' => 3,
                'is_active' => true,
            ]
        );
    }
}