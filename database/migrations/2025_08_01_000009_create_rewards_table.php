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
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->index('task_id');

            $table->foreignId('giver_id')->constrained('users')->onDelete('cascade');
            $table->index('giver_id');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->index('receiver_id');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->index('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};