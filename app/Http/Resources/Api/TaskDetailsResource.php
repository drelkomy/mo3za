<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskDetailsResource extends JsonResource
{
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
            'total_stages' => $this->total_stages,
            'creator' => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ],
            'receiver' => $this->whenLoaded('receiver', function() {
                return [
                    'id' => $this->receiver->id,
                    'name' => $this->receiver->name,
                ];
            }),
            'team' => $this->whenLoaded('team', function() {
                return [
                    'id' => $this->team->id,
                    'name' => $this->team->name,
                ];
            }),
            'stages' => $this->whenLoaded('stages', function() {
                return $this->stages->map(function($stage) {
                    return [
                        'id' => $stage->id,
                        'stage_number' => $stage->stage_number,
                        'title' => $stage->title,
                        'description' => $stage->description,
                        'status' => $stage->status,
                        'completed_at' => $stage->completed_at?->format('Y-m-d H:i:s'),
                    ];
                })->sortBy('stage_number')->values();
            }),
            'reward_amount' => $this->reward_amount,
            'reward_description' => $this->reward_description,
            'reward_type' => $this->reward_type,
        ];
    }
}