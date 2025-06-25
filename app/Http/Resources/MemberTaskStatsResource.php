<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MemberTaskStatsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this['member_id'],
            'name' => $this['member_name'],
            'total_tasks' => $this['total_tasks'],
            'completed_tasks' => $this['completed_tasks'],
            'in_progress_tasks' => $this['in_progress_tasks'],
            'pending_tasks' => $this['pending_tasks'],
            'completion_rate' => $this['completion_rate'],
            'tasks_by_status' => [
                'completed' => $this['completed_tasks'],
                'in_progress' => $this['in_progress_tasks'],
                'pending' => $this['pending_tasks']
            ],
            'performance_metrics' => [
                'completion_percentage' => $this['completion_rate'] . '%',
                'average_progress' => $this['average_progress'] . '%'
            ]
        ];
    }
}