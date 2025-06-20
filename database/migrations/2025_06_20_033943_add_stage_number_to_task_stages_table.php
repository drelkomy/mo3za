<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_stages', function (Blueprint $table) {
            if (!Schema::hasColumn('task_stages', 'stage_number')) {
                $table->integer('stage_number')->after('task_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_stages', function (Blueprint $table) {
            $table->dropColumn('stage_number');
        });
    }
};