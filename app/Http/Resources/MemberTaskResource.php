<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MemberTaskResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'progress' => $this->progress,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
            'stages' => $this->stages->map(function($stage) {
                return [
                    'id' => $stage->id,
                    'title' => $stage->title,
                    'status' => $stage->status,
                    'stage_number' => $stage->stage_number
                ];
            })
        ];
    }
}