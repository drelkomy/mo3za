<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_stages', function (Blueprint $table) {
            if (!Schema::hasColumn('task_stages', 'status')) {
                $table->enum('status', ['pending', 'completed'])->default('pending')->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_stages', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};