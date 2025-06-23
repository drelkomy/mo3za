<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('reward_distributed')->default(false)->after('reward_amount');
            $table->timestamp('reward_distributed_at')->nullable()->after('reward_distributed');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['reward_distributed', 'reward_distributed_at']);
        });
    }
};