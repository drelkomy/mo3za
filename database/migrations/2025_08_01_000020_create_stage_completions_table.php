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
        Schema::create('stage_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_stage_id')->constrained('task_stages')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->string('proof_path')->nullable(); // file path for document/video/image
            $table->string('proof_type')->nullable(); // image, video, document, etc.
            $table->timestamps();
            $table->unique(['task_stage_id', 'user_id']);
            $table->index(['task_stage_id', 'user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stage_completions');
    }
};
