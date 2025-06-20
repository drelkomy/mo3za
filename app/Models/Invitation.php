<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id', 'sender_id', 'email', 'token', 'status', 'accepted_at'
    ];

    protected $casts = ['accepted_at' => 'datetime'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}