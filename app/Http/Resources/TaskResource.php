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
            'description' => strip_tags($this->description),
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
                        'proof_notes' => $stage->proof_notes,
                        'proof_files' => $stage->proof_files,
                        'start_date' => $stage->start_date ? $stage->start_date->format('Y-m-d') : null,
                        'end_date' => $stage->end_date ? $stage->end_date->format('Y-m-d') : null,
                        'completed_at' => $stage->completed_at ? $stage->completed_at->format('Y-m-d H:i:s') : null,
                    ];
                });
            }),
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
            'duration_days' => $this->duration_days,
            'priority' => $this->priority,
            'reward_type' => $this->reward_type,
            'reward_amount' => $this->reward_amount,
            'reward_description' => $this->reward_description,
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
            
            // إذا كان المسار نسبيًا، استخدم المسار الكامل للتخزين
            return asset('storage/' . $user->avatar_url);
        }
        
        return null;
    }
}
