<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrialStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'has_trial' => $this->has_trial,
            'is_trial_active' => $this->is_trial_active,
            'trial_subscription' => $this->when($this->trial_subscription, function() {
                return [
                    'id' => $this->trial_subscription->id,
                    'status' => $this->trial_subscription->status,
                    'start_date' => $this->trial_subscription->start_date?->format('Y-m-d'),
                    'end_date' => $this->trial_subscription->end_date?->format('Y-m-d'),
                    'max_tasks' => $this->trial_subscription->max_tasks,
                    'tasks_created' => $this->trial_subscription->tasks_created,
                    'remaining_tasks' => max(0, $this->trial_subscription->max_tasks - $this->trial_subscription->tasks_created),
                    'max_milestones_per_task' => $this->trial_subscription->max_milestones_per_task,
                    'days_remaining' => $this->trial_subscription->end_date ? 
                        max(0, now()->diffInDays($this->trial_subscription->end_date, false)) : 0,
                ];
            }),
            'can_renew_trial' => !$this->has_trial,
        ];
    }
}