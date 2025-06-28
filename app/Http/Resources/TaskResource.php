<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'progress' => $this->progress,
            'due_date' => $this->due_date,
            'created_at' => $this->created_at->format('Y-m-d'),
            'creator' => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                // يتم إنشاء رابط الصورة الديناميكي بناءً على قيمة avatar_url
                'avatar_url' => $this->getCreatorAvatarUrl(),
            ],
            'stages' => $this->whenLoaded('stages', function() {
                // تحميل المراحل بشكل كسول لتقليل الضغط على السيرفر
                return TaskStageResource::collection($this->stages->sortBy('stage_number')->take(5));
            }),
        ];
    }
    
    /**
     * الحصول على رابط الصورة الشخصية لمنشئ المهمة
     */
    private function getCreatorAvatarUrl(): ?string
    {
        if (empty($this->creator->avatar_url)) {
            return "https://www.moezez.com/storage/avatars/default.png";
        }
        
        // إذا كان الرابط يحتوي على http بالفعل، تنظيفه من التكرار
        if (str_starts_with($this->creator->avatar_url, 'http')) {
            // إزالة التكرار إذا وجد
            $url = $this->creator->avatar_url;
            if (str_contains($url, 'storage/https://')) {
                $url = str_replace('https://www.moezez.com/storage/https://www.moezez.com/storage/', 'https://www.moezez.com/storage/', $url);
            }
            return $url;
        }
        
        // إذا كان يبدأ بـ avatars/ مباشرة
        if (str_starts_with($this->creator->avatar_url, 'avatars/')) {
            return 'https://www.moezez.com/storage/' . $this->creator->avatar_url;
        }
        
        // إذا كان يحتوي على storage/ بالفعل
        if (str_starts_with($this->creator->avatar_url, 'storage/')) {
            return 'https://www.moezez.com/' . $this->creator->avatar_url;
        }
        
        // الحالة الافتراضية
        return 'https://www.moezez.com/storage/' . $this->creator->avatar_url;
    }
}
