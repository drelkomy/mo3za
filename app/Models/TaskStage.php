<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\StageStatus;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TaskStage extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'task_id', 'stage_number', 'title', 'description', 'status', 'completed_at', 'due_date', 'notes',
        'proof_notes', 'proof_files'
    ];

    protected $casts = [
        'stage_number' => 'integer',
        'completed_at' => 'datetime',
        'due_date' => 'date',
        'status' => 'string',
        'proof_files' => 'json'
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function markAsCompleted(): void
    {
        if ($this->status === 'completed') {
            return; // لا داعي للتحديث
        }

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->task->updateProgress();
    }
}
