<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberStatsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'email' => $this['email'],
            'avatar_url' => $this['avatar_url'],
            'total_tasks' => $this['total_tasks'],
            'completed_tasks' => $this['completed_tasks'],
            'pending_tasks' => $this['pending_tasks'],
            'in_progress_tasks' => $this['in_progress_tasks'],
            'completion_rate' => $this['completion_rate'] ?? 0,
            'completion_percentage_margin' => $this['completion_percentage_margin'],
            'average_progress' => $this['average_progress'] ?? 0,
            'tasks' => $this['tasks'] ?? []
        ];
    }
}