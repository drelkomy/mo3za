<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // نهج بديل: إنشاء جدول جديد بالقيد الفريد المطلوب ثم نقل البيانات
        Schema::create('join_requests_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();
            
            // القيد الفريد الجديد
            $table->unique(['user_id', 'team_id', 'status']);
        });
        
        // نقل البيانات
        DB::statement('INSERT INTO join_requests_new (id, user_id, team_id, status, created_at, updated_at) 
                      SELECT id, user_id, team_id, status, created_at, updated_at FROM join_requests');
        
        // حذف الجدول القديم
        Schema::dropIfExists('join_requests');
        
        // إعادة تسمية الجدول الجديد
        Schema::rename('join_requests_new', 'join_requests');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // إعادة إنشاء الجدول بالقيد الفريد الأصلي
        Schema::create('join_requests_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();
            
            // القيد الفريد الأصلي
            $table->unique(['user_id', 'team_id']);
        });
        
        // نقل البيانات
        DB::statement('INSERT INTO join_requests_old (id, user_id, team_id, status, created_at, updated_at) 
                      SELECT id, user_id, team_id, status, created_at, updated_at FROM join_requests');
        
        // حذف الجدول الجديد
        Schema::dropIfExists('join_requests');
        
        // إعادة تسمية الجدول القديم
        Schema::rename('join_requests_old', 'join_requests');
    }
};