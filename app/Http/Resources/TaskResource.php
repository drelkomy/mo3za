<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'progress' => $this->progress,
            'due_date' => $this->due_date,
            'created_at' => $this->created_at->format('Y-m-d'),
            'stages_count' => $this->stages_count,
            'completed_stages' => $this->completed_stages
        ];
    }
}