<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Area;
use App\Models\City;
use App\Models\Package;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run modular seeders
        $this->call([
            AreasAndCitiesSeeder::class,
            PackageSeeder::class,
            RolesAndPermissionsSeeder::class,
            AssignAdminRoleSeeder::class,
            AdminUserSeeder::class,
            TestMembersSeeder::class,
        ]);
    }
}