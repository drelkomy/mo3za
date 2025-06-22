<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Package;

return new class extends Migration
{
    public function up(): void
    {
        Package::where('id', 1)->update(['is_trial' => true]);
    }

    public function down(): void
    {
        Package::where('id', 1)->update(['is_trial' => false]);
    }
};