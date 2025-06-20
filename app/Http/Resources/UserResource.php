<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
          
            
            // معلومات إضافية
            'has_active_subscription' => $this->whenLoaded('subscriptions', function () {
                return $this->hasActiveSubscription();
            }),
            
            // العلاقات
            'owned_teams' => $this->whenLoaded('ownedTeams', function () {
                return $this->ownedTeams;
            }),
            'teams' => $this->whenLoaded('teams', function () {
                return $this->teams;
            }),
        ];
    }
}