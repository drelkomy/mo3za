<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('priority', ['urgent', 'normal'])->default('normal')->after('status');
            $table->enum('task_status', ['in_progress', 'cancelled', 'completed'])->default('in_progress')->after('priority');
            $table->boolean('is_multiple')->default(false)->after('task_status');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['priority', 'task_status', 'is_multiple']);
        });
    }
};