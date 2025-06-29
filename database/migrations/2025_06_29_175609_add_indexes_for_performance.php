<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'end_date'], 'idx_user_active_subscription');
            $table->index(['user_id', 'created_at'], 'idx_user_subscription_history');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->index(['is_active', 'is_trial'], 'idx_package_active_trial');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_user_active_subscription');
            $table->dropIndex('idx_user_subscription_history');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex('idx_package_active_trial');
        });
    }
};