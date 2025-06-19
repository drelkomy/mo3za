<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TaskStage extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'task_id',
        'title',
        'description',
        'order',
        'status',
        'due_date',
        'completed_at',
        'reward_percentage',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'order' => 'integer',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'reward_percentage' => 'decimal:2',
    ];

    /**
     * Get the task that owns the stage.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

}