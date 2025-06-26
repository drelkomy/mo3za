<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionCreateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'subscription_id' => $this->id,
            'status' => $this->status,
            'package' => [
                'name' => $this->package->name,
                'price' => $this->package->price,
                'is_trial' => $this->package->is_trial
            ],
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'payment_url' => $this->when(
                !$this->package->is_trial && $this->package->price > 0,
                $this->payment_url ?? null
            )
        ];
    }
}