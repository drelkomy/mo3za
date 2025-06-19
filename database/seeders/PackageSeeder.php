<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'الباقة الأساسية',
                'price' => 99.99,
                'max_tasks' => 10,
                'max_participants' => 5,
                'max_milestones_per_task' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'الباقة المتقدمة',
                'price' => 199.99,
                'max_tasks' => 50,
                'max_participants' => 25,
                'max_milestones_per_task' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'الباقة الاحترافية',
                'price' => 299.99,
                'max_tasks' => 100,
                'max_participants' => 50,
                'max_milestones_per_task' => 15,
                'is_active' => true,
            ],
            [
                'name' => 'باقة تجريبية',
                'price' => 0,
                'is_trial' => true,
                'max_participants' => 2,
                'max_tasks' => 3,
                'max_milestones_per_task' => 2,
                'is_active' => true,
        ],

        ];

        foreach ($packages as $package) {
            Package::create($package);
        }
    }
}