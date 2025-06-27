<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\RewardStatus;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount', 'status', 'giver_id', 'receiver_id', 'task_id', 'description', 'reward_distributed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status' => 'string',
        'reward_distributed_at' => 'datetime',
    ];

    public function giver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'giver_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function approve(): bool
    {
        return $this->update(['status' => 'completed']);
    }
}
