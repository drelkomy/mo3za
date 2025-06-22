<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Task extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title', 'description', 'terms', 'comment', 'status', 'progress', 
        'is_active', 'creator_id', 'receiver_id', 'subscription_id', 'team_id',
        'reward_amount', 'reward_description', 'start_date', 'due_date', 
        'completed_at', 'duration_days', 'total_stages', 'priority', 
        'task_status', 'is_multiple', 'reward_type', 'selected_members'
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
        'selected_members' => 'array',
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

    public function updateProgress(): void
    {
        if ($this->total_stages > 0) {
            $completedStages = $this->stages()->where('status', 'completed')->count();
            $progress = ($completedStages / $this->total_stages) * 100;
            
            $this->progress = round($progress);

            if ($this->progress == 100) {
                $this->status = 'completed';
            } else {
                // If progress is > 0 but not 100, it's in progress.
                // If progress is 0, it remains as it was (e.g., pending).
                if ($this->progress > 0) {
                    $this->status = 'in_progress';
                }
            }

        } else {
            // If there are no stages, we can consider it 100% complete by default
            // or handle as per business logic. For now, let's set to 0.
            $this->progress = 0;
        }

        $this->save();
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The members (users) that are assigned to the task.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user', 'task_id', 'user_id')
            ->withPivot('is_primary', 'status', 'completion_percentage', 'notes')
            ->withTimestamps();
    }
}