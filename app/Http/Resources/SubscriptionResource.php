<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package_name' => $this->package->name,
            'package_type' => $this->package->is_trial ? 'تجريبية' : 'مدفوعة',
            'max_tasks' => $this->package->max_tasks,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'created_at' => $this->created_at->format('Y-m-d H:i:s')
        ];
    }
}