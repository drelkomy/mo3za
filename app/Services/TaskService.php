<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskStage;

class TaskService
{
    public function createTaskStages(Task $task): void
    {
        $startDate = \Carbon\Carbon::parse($task->start_date);
        $endDate = \Carbon\Carbon::parse($task->due_date);
        $daysPerStage = $task->duration_days / $task->total_stages;
        
        for ($i = 1; $i <= $task->total_stages; $i++) {
            $stageStartDate = $startDate->copy()->addDays(floor(($i - 1) * $daysPerStage));
            
            // ضبط المرحلة الأخيرة لتنتهي في التاريخ المحدد
            if ($i === $task->total_stages) {
                $stageEndDate = $endDate;
            } else {
                $stageEndDate = $startDate->copy()->addDays(ceil($i * $daysPerStage) - 1);
            }
            
            TaskStage::create([
                'task_id' => $task->id,
                'stage_number' => $i,
                'title' => "المرحلة {$i}",
                'description' => "وصف المرحلة {$i} من المهمة: {$task->title}",
                'status' => 'pending',
                'start_date' => $stageStartDate,
                'due_date' => $stageEndDate,
            ]);
        }
    }

    public function updateTaskProgress(Task $task): void
    {
        $stages = $task->stages;
        $completed = $stages->where('status', 'completed')->count();
        $total = $stages->count();
        $progress = $total > 0 ? ($completed / $total) * 100 : 0;
        
        $task->fill([
            'progress' => round($progress),
            'status' => match (true) {
                $progress === 100.0 => 'completed',
                $progress > 0 => 'in_progress',
                default => 'pending',
            },
            'completed_at' => $progress === 100.0 ? now() : null,
        ])->save();
    }

    public function completeStage(TaskStage $stage, string $proofNotes = null): void
    {
        if ($stage->status !== 'completed') {
            $stage->update([
                'status' => 'completed',
                'completed_at' => now(),
                'proof_notes' => $proofNotes,
            ]);

            $this->updateTaskProgress($stage->task);
        }
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
