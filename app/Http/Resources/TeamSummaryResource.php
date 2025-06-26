<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeamSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'total_tasks' => $this['total_tasks'],
            'completed_tasks' => $this['completed_tasks'],
            'pending_tasks' => $this['pending_tasks'],
            'in_progress_tasks' => $this['in_progress_tasks'],
            'completion_rate' => $this['completion_rate']
        ];
    }
}