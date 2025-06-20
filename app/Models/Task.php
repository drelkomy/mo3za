<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Task extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title', 'description', 'terms', 'comment', 'status', 'progress', 
        'is_active', 'creator_id', 'receiver_id', 'subscription_id', 
        'reward_amount', 'reward_description', 'start_date', 'due_date', 
        'completed_at', 'duration_days', 'total_stages', 'priority', 
        'task_status', 'is_multiple', 'reward_type', 'selected_participants'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'progress' => 'integer',
        'reward_amount' => 'decimal:2',
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'duration_days' => 'integer',
        'total_stages' => 'integer',
        'is_multiple' => 'boolean',
        'selected_participants' => 'array',
    ];

    // تحسين الأداء بإضافة العلاقات التي يتم استخدامها بشكل متكرر
    protected $with = ['creator', 'receiver'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(TaskStage::class)->orderBy('stage_number');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($task) {
            $task->start_date = $task->start_date ?? now()->toDateString();
            if ($task->duration_days && $task->start_date) {
                $task->due_date = \Carbon\Carbon::parse($task->start_date)->addDays($task->duration_days);
            }
        });
        
        static::created(function ($task) {
            $task->createStages();
            if ($task->is_multiple) {
                $task->createMultipleTasks();
            }
        });
    }
    
    public function createStages()
    {
        if ($this->total_stages > 0) {
            $stages = [];
            for ($i = 1; $i <= $this->total_stages; $i++) {
                $stages[] = [
                    'task_id' => $this->id,
                    'stage_number' => $i,
                    'title' => "المرحلة {$i}",
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            // استخدام insert بدلاً من create لتحسين الأداء
            TaskStage::insert($stages);
        }
    }
    
    public function createMultipleTasks()
    {
        if ($this->selected_participants) {
            $tasks = [];
            foreach ($this->selected_participants as $participantId) {
                $newTask = $this->replicate()->fill([
                    'is_multiple' => false,
                    'receiver_id' => $participantId,
                    'selected_participants' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray();
                $tasks[] = $newTask;
            }
            // استخدام insert بدلاً من save لتحسين الأداء
            if (!empty($tasks)) {
                self::insert($tasks);
            }
        }
    }
    
    public function updateProgress()
    {
        $totalStages = $this->total_stages;
        if ($totalStages === 0) {
            $this->update(['progress' => 0]);
            return;
        }
        
        // استخدام cache للاستعلامات المتكررة
        $completedStages = $this->stages()->where('status', 'completed')->count();
        $progress = round(($completedStages / $totalStages) * 100);
        
        $this->update([
            'progress' => $progress,
            'status' => $progress === 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'pending')
        ]);
    }
}