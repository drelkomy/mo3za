<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MemberTaskStatsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'member_id' => $this['member_id'],
            'member_name' => $this['member_name'],
            'total_tasks' => $this['total_tasks'],
            'completed_tasks' => $this['completed_tasks'],
            'in_progress_tasks' => $this['in_progress_tasks'],
            'pending_tasks' => $this['pending_tasks'],
            'completion_rate' => $this['completion_rate'],
            'average_progress' => $this['average_progress']
        ];
    }
}