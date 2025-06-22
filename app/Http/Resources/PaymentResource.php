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
            'package_name' => $this->package->name,
            'amount' => $this->price_paid,
            'status' => $this->status,
            'date' => $this->created_at->format('Y-m-d H:i:s')
        ];
    }
}