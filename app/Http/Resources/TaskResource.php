<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'progress' => $this->progress,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'duration_days' => $this->duration_days,
            'total_stages' => $this->total_stages,
            'reward_type' => $this->reward_type,
            'reward_amount' => $this->reward_amount,
            'reward_description' => $this->reward_description,
            'assigned_to' => $this->whenLoaded('assignedTo', fn() => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ]),
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'stages_count' => $this->stages_count ?? $this->stages()->count(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}