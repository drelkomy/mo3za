<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionWithTasksResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $maxTasks = $this->package->max_tasks;
        $tasksCreated = $this->tasks_created ?? 0;
        $remainingTasks = $maxTasks - $tasksCreated;
        $usagePercentage = $maxTasks > 0 ? round(($tasksCreated / $maxTasks) * 100) : 0;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'start_date' => $this->start_date?->format('Y-m-d') ?? $this->created_at?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d') ?? $this->ends_at?->format('Y-m-d') ?? 'غير محدد',
            'package' => [
                'id' => $this->package->id,
                'name' => $this->package->name,
                'is_trial' => $this->package->is_trial,
                'max_tasks' => $maxTasks,
            ],
            'usage' => [
                'tasks_created' => $tasksCreated,
                'remaining_tasks' => $remainingTasks,
                'max_tasks' => $maxTasks,
                'usage_percentage' => $usagePercentage,
            ],
            'tasks' => $this->whenLoaded('tasks', function () {
                return $this->tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title ?? 'غير محدد',
                        'created_at' => $task->created_at?->format('Y-m-d H:i:s'),
                        'status' => $task->status ?? 'غير محدد',
                    ];
                });
            }, []),
            'subscription_status_note' => $this->status === 'active' ? 'هذا الاشتراك نشط' : 'هذا الاشتراك منتهي أو ملغى',
        ];
    }
}
