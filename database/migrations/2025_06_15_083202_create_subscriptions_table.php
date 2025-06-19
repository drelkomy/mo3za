<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->index('user_id');
            $table->foreignId('package_id')->constrained()->onDelete('cascade');
            $table->index('package_id');
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('set null');
            $table->index('payment_id');
            $table->string('status')->default('active');
            $table->index('status');
            $table->decimal('price_paid', 8, 2);
            $table->integer('tasks_created')->default(0);
            $table->integer('participants_created')->default(0);
            $table->integer('max_tasks')->default(0);
            $table->integer('max_participants')->default(0);
            $table->integer('max_milestones_per_task')->default(0);
            $table->integer('previous_tasks_completed')->default(0);
            $table->integer('previous_tasks_pending')->default(0);
            $table->decimal('previous_rewards_amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
