<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JoinRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_text' => match($this->status) {
                'pending' => 'في الانتظار',
                'accepted' => 'مقبول',
                'rejected' => 'مرفوض',
                default => $this->status,
            },
            'team' => [
                'id' => $this->whenLoaded('team', fn() => $this->team->id),
                'name' => $this->whenLoaded('team', fn() => $this->team->name),
            ],
            'user' => $this->when($this->relationLoaded('user'), [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'inviter' => $this->when($this->relationLoaded('team') && $this->team?->relationLoaded('owner'), [
                'id' => $this->team?->owner?->id,
                'name' => $this->team?->owner?->name,
                'email' => $this->team?->owner?->email,
            ]),
        ];
    }
}