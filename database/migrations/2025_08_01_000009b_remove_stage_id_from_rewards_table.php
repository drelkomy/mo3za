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
        Schema::table('rewards', function (Blueprint $table) {
            // Drop foreign key if exists (MySQL)
            try {
                \DB::statement('ALTER TABLE rewards DROP FOREIGN KEY rewards_stage_id_foreign');
            } catch (\Exception $e) {}
            // Drop column if exists
            if (\Schema::hasColumn('rewards', 'stage_id')) {
                $table->dropColumn('stage_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->foreign('stage_id')->references('id')->on('task_stages')->onDelete('set null');
        });
    }
};
