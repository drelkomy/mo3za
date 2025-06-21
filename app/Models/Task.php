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


}