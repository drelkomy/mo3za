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
            'user' => [
                'id' => $this->whenLoaded('user', fn() => $this->user->id),
                'name' => $this->whenLoaded('user', fn() => $this->user->name),
                'email' => $this->whenLoaded('user', fn() => $this->user->email),
            ],
        ];
    }
}