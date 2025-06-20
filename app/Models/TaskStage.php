<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TaskStage extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'task_id', 'stage_number', 'title', 'description', 
        'status', 'completed_at', 'proof_notes', 'attachments'
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'stage_number' => 'integer',
    ];

    // تحسين الأداء بإضافة الفهارس
    protected static function boot()
    {
        parent::boot();
        
        // ترتيب المراحل افتراضياً حسب رقم المرحلة
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('stage_number', 'asc');
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
    
    public function markAsCompleted()
    {
        // استخدام معاملة واحدة لتحديث المرحلة
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
        
        // تحديث تقدم المهمة تلقائياً
        $this->task->updateProgress();
    }
}