<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrentSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'start_date' => $this->start_date?->format('Y-m-d') ?? $this->created_at?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d') ?? $this->ends_at?->format('Y-m-d') ?? 'غير محدد',
            'total_days' => ($this->start_date ?? $this->created_at) && ($this->end_date ?? $this->ends_at) ? ($this->start_date ?? $this->created_at)->diffInDays($this->end_date ?? $this->ends_at) : 'غير محدد',
            'price_paid' => $this->price_paid,
            'package' => [
                'id' => $this->package->id,
                'name' => $this->package->name,
                'is_trial' => $this->package->is_trial,
                'max_tasks' => $this->package->max_tasks,
                'max_milestones_per_task' => $this->package->max_milestones_per_task,
            ],
            'usage' => [
                'tasks_created' => $this->tasks_created ?? 0,
                'remaining_tasks' => max(0, $this->package->max_tasks - ($this->tasks_created ?? 0)),
                'max_tasks' => $this->package->max_tasks,
                'usage_percentage' => $this->package->max_tasks > 0 ? 
                    round((($this->tasks_created ?? 0) / $this->package->max_tasks) * 100, 1) : 0,
                'note' => 'يتم حساب المهام المستهلكة من تاريخ الاشتراك الحالي فقط.',
            ],
            'days_remaining' => ($this->end_date ?? $this->ends_at) ? 
                max(0, now()->diffInDays($this->end_date ?? $this->ends_at, false)) : 'غير محدد',
            'is_active' => $this->status === 'active' && 
                         (!$this->end_date || $this->end_date->isFuture()),
        ];
    }
}
