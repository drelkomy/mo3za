<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'message' => $this->message,
            'status' => $this->status,
            'token' => $this->token,
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'team' => $this->whenLoaded('team', fn() => [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ]),
            'inviter' => $this->whenLoaded('inviter', fn() => [
                'id' => $this->inviter->id,
                'name' => $this->inviter->name,
            ]),
            'invitee' => $this->whenLoaded('invitee', fn() => [
                'id' => $this->invitee->id,
                'name' => $this->invitee->name,
            ]),
            'is_expired' => $this->expires_at && $this->expires_at->isPast(),
            'days_remaining' => $this->expires_at ? 
                max(0, now()->diffInDays($this->expires_at, false)) : null,
        ];
    }
}