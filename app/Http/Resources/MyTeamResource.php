<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'owner' => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ],
            'members_count' => $this->members_count ?? 0,
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}