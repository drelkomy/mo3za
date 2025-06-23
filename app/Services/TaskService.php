<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskStage;

class TaskService
{
    public function createTaskStages(Task $task): void
    {
        for ($i = 1; $i <= $task->total_stages; $i++) {
            TaskStage::create([
                'task_id' => $task->id,
                'stage_number' => $i,
                'title' => "المرحلة {$i}",
                'description' => "وصف المرحلة {$i} من المهمة: {$task->title}",
                'status' => 'pending',
            ]);
        }
    }

    public function updateTaskProgress(Task $task): void
    {
        $completedStages = $task->stages()->where('status', 'completed')->count();
        $totalStages = $task->stages()->count();
        
        $progress = $totalStages > 0 ? ($completedStages / $totalStages) * 100 : 0;
        
        $task->update([
            'progress' => round($progress),
            'status' => $progress == 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'pending'),
            'completed_at' => $progress == 100 ? now() : null,
        ]);
    }

    public function completeStage(TaskStage $stage, string $proofNotes = null): void
    {
        $stage->update([
            'status' => 'completed',
            'completed_at' => now(),
            'proof_notes' => $proofNotes,
        ]);

        $this->updateTaskProgress($stage->task);
    }

    public function completeTaskByLeader(Task $task, bool $distributeReward = true): void
    {
        $task->update([
            'status' => 'completed',
            'progress' => 100,
            'completed_at' => now(),
        ]);

        if ($distributeReward && $task->reward_amount > 0 && $task->assigned_to) {
            $this->distributeReward($task);
        }
    }

    private function distributeReward(Task $task): void
    {
        \App\Models\Reward::create([
            'task_id' => $task->id,
            'user_id' => $task->assigned_to,
            'amount' => $task->reward_amount,
            'status' => 'received',
            'distributed_at' => now(),
            'received_at' => now(),
            'distributed_by' => $task->creator_id,
        ]);

        $task->update([
            'reward_distributed' => true,
            'reward_distributed_at' => now(),
        ]);
    }
}
