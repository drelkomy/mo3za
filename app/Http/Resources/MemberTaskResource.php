<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MemberTaskResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'progress' => $this->progress,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'created_at' => $this->created_at->format('Y-m-d'),
            'stages_count' => $this->stages?->count() ?? 0,
            'completed_stages' => $this->stages?->where('status', 'completed')->count() ?? 0
        ];
    }
}