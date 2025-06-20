<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id', 'giver_id', 'receiver_id', 'amount', 'status', 'notes'
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'giver_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}