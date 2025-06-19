<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'task_id',
        'giver_id',
        'receiver_id',
        'amount',
        'status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the task that owns the reward.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }


    /**
     * Get the giver of the reward.
     */
    public function giver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'giver_id');
    }

    /**
     * Get the receiver of the reward.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}