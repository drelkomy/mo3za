<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package_name' => $this->package->name,
            'package_type' => $this->package->is_trial ? 'تجريبية' : 'مدفوعة',
            'max_tasks' => $this->package->max_tasks,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'usage' => [
                'tasks_created' => $this->tasks_created ?? 0,
                'remaining_tasks' => max(0, $this->package->max_tasks - ($this->tasks_created ?? 0)),
                'usage_percentage' => $this->package->max_tasks > 0 ? 
                    round((($this->tasks_created ?? 0) / $this->package->max_tasks) * 100, 1) : 0,
            ],
            'previous_tasks_completed' => $this->previous_tasks_completed ?? 0,
            'previous_tasks_pending' => $this->previous_tasks_pending ?? 0
        ];
    }
}
