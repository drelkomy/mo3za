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
            $table->integer('progress')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->index('creator_id');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->index('receiver_id');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->onDelete('set null');
            $table->index('subscription_id');
            $table->decimal('reward_amount', 10, 2)->nullable();
            $table->string('reward_description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('completed_at')->nullable();
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