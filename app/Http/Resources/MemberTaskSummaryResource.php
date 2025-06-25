<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MemberTaskSummaryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this['id'],
            'title' => $this['title'],
            'status' => $this['status'],
            'progress' => $this['progress'],
            'due_date' => $this['due_date'],
            'created_at' => $this['created_at'],
            'stages_count' => $this['stages_count'],
            'completed_stages' => $this['completed_stages']
        ];
    }
}