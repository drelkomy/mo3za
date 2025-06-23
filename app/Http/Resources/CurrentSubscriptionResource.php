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
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
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
                'usage_percentage' => $this->package->max_tasks > 0 ? 
                    round((($this->tasks_created ?? 0) / $this->package->max_tasks) * 100, 1) : 0,
            ],
            'days_remaining' => $this->end_date ? 
                max(0, now()->diffInDays($this->end_date, false)) : null,
            'is_active' => $this->status === 'active' && 
                         (!$this->end_date || $this->end_date->isFuture()),
        ];
    }
}