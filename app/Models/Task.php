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
        return $this->hasMany(TaskStage::class);
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
            for ($i = 1; $i <= $this->total_stages; $i++) {
                \App\Models\TaskStage::create([
                    'task_id' => $this->id,
                    'stage_number' => $i,
                    'title' => "المرحلة {$i}",
                    'status' => 'pending'
                ]);
            }
        }
    }
    
    public function createMultipleTasks()
    {
        if ($this->selected_participants) {
            foreach ($this->selected_participants as $participantId) {
                $newTask = $this->replicate();
                $newTask->is_multiple = false;
                $newTask->receiver_id = $participantId;
                $newTask->selected_participants = null;
                $newTask->save();
            }
        }
    }
    
    public function updateProgress()
    {
        $totalStages = $this->stages()->count();
        if ($totalStages === 0) {
            $this->update(['progress' => 0]);
            return;
        }
        
        $completedStages = $this->stages()->where('status', 'completed')->count();
        $progress = round(($completedStages / $totalStages) * 100);
        
        $this->update([
            'progress' => $progress,
            'status' => $progress === 100 ? 'completed' : 'in_progress'
        ]);
    }
}