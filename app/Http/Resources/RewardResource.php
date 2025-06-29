<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RewardResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'notes' => $this->notes,
            'status' => $this->status,
            'user' => $this->whenLoaded('receiver', function () {
                return [
                    'id' => $this->receiver->id,
                    'name' => $this->receiver->name,
                    'avatar_url' => $this->getAvatarUrl($this->receiver),
                ];
            }),
            'task' => $this->whenLoaded('task', function () {
                return [
                    'id' => $this->task->id,
                    'title' => $this->task->title,
                ];
            }),
            'distributed_by' => $this->whenLoaded('giver', function () {
                return [
                    'id' => $this->giver->id,
                    'name' => $this->giver->name,
                    'avatar_url' => $this->getAvatarUrl($this->giver),
                ];
            }),
            'reward_type' => $this->reward_type ?? ($this->whenLoaded('task', function () {
                return $this->task->reward_type ?? 'نقدي';
            }, 'نقدي')),
            'reward_description' => $this->reward_description ?? ($this->whenLoaded('task', function () {
                return $this->task->reward_description ?? '';
            }, '')),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
    
    private function getAvatarUrl($user)
    {
        if (!$user) return null;
        
        if ($user->avatar_url) {
            return \Illuminate\Support\Facades\Storage::url($user->avatar_url);
        }
        
        return null;
    }
}
