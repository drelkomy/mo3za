<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TaskStageResource;

class TaskDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'progress' => $this->progress,
            'priority' => $this->priority,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'total_stages' => $this->total_stages,
            'creator' => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ],
            'receiver' => $this->when($this->receiver, function() {
                return [
                    'id' => $this->receiver->id,
                    'name' => $this->receiver->name,
                ];
            }),
            'team' => $this->when($this->team, function() {
                return [
                    'id' => $this->team->id,
                    'name' => $this->team->name,
                ];
            }),
            'stages' => $this->whenLoaded('stages', function() {
                return TaskStageResource::collection($this->stages->sortBy('stage_number'));
            }),
            'reward_amount' => $this->reward_amount,
            'reward_description' => $this->reward_description,
            'reward_type' => $this->reward_type,
            'reward_distributed' => $this->reward_distributed,
        ];
    }
}