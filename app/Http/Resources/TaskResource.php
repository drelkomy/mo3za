<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TaskResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'progress' => $this->progress,
            'receiver' => $this->whenLoaded('receiver', function () {
                return [
                    'id' => $this->receiver->id,
                    'name' => $this->receiver->name,
                    'avatar_url' => $this->getAvatarUrl($this->receiver),
                ];
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),
            'stages_count' => $this->when(isset($this->stages_count), $this->stages_count),
            'stages' => $this->whenLoaded('stages', function () {
                return $this->stages->map(function ($stage) {
                    return [
                        'id' => $stage->id,
                        'title' => $stage->title,
                        'description' => $stage->description,
                        'status' => $stage->status,
                        'stage_number' => $stage->stage_number,
                        'completed_at' => $stage->completed_at ? $stage->completed_at->format('Y-m-d H:i:s') : null,
                    ];
                });
            }),
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
    
    private function getAvatarUrl($user)
    {
        if (!$user) return null;
        
        if ($user->avatar_url) {
            // التحقق من أن المسار يحتوي على الرابط الكامل
            if (strpos($user->avatar_url, 'http') === 0) {
                return $user->avatar_url;
            }
            
            // إذا كان المسار نسبيًا
            return "https://www.moezez.com/storage/" . basename($user->avatar_url);
        }
        
        return null;
    }
}