<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'status' => $this->status,
            'distributed_at' => $this->distributed_at?->format('Y-m-d H:i:s'),
            'received_at' => $this->received_at?->format('Y-m-d H:i:s'),
            'task' => $this->whenLoaded('task', fn() => [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ]),
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'distributed_by' => $this->whenLoaded('distributedBy', fn() => [
                'id' => $this->distributedBy->id,
                'name' => $this->distributedBy->name,
            ]),
        ];
    }
}