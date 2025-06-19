<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StageCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_stage_id',
        'user_id',
        'status',
        'notes',
        'proof_path',
        'proof_type',
    ];

    /**
     * Get the stage that this completion belongs to.
     */
    public function stage()
    {
        return $this->belongsTo(TaskStage::class, 'task_stage_id');
    }

    /**
     * Get the user who completed the stage.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
