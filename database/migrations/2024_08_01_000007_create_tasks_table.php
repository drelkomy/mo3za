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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('terms')->nullable();
            $table->text('comment')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->index('status');
            $table->enum('priority', ['urgent', 'normal'])->default('normal');
            $table->enum('task_status', ['in_progress', 'cancelled', 'completed'])->default('in_progress');
            $table->boolean('is_multiple')->default(false);
            $table->text('selected_members')->nullable();
            $table->integer('progress')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->index('creator_id');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->index('receiver_id');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->onDelete('set null');
            $table->index('subscription_id');
            $table->decimal('reward_amount', 10, 2)->nullable();
            $table->enum('reward_type', ['cash', 'other'])->default('cash');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->integer('duration_days')->default(7);
            $table->integer('total_stages')->default(3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};