<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobilePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'subscription_id' => $this->id,
            'status' => $this->status,
            'message' => 'تم تفعيل الباقة بنجاح'
        ];
    }
}