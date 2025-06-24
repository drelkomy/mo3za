<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->id, // Using subscription ID as order_id since actual order_id might not be available
            'amount' => $this->amount ?? 0.00,
            'currency' => 'SAR', // Defaulting to SAR as currency information is not directly available in subscription data
            'status' => $this->status,
            'payment_url' => $this->when($this->status === 'pending', $this->payment_url ?? null),
            'package_type' => $this->whenLoaded('package', fn () => $this->package->name ?? 'غير متوفر'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
