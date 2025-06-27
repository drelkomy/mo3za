<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'completion_percentage_margin' => $this->completion_percentage_margin,
            'tasks' => TaskResource::collection($this->whenLoaded('tasks'))
        ];
    }
}