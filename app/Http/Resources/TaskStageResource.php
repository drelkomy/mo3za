<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskStageResource extends JsonResource
{
    public function toArray($request)
    {
        $proofFiles = null;
        if ($this->proof_files) {
            $files = is_string($this->proof_files) ? json_decode($this->proof_files, true) : $this->proof_files;
            
            if ($files && is_array($files)) {
                // إذا كان هناك ملف صورة
                if (isset($files['path'])) {
                    $proofFiles = $files['url'] ?? asset('storage/' . $files['path']);
                }
                // إذا كان نص فقط
                elseif (isset($files['type']) && $files['type'] === 'text_only') {
                    $proofFiles = $files['notes'] ?? 'تم إكمال المرحلة';
                }
            }
        }
        
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'stage_number' => $this->stage_number,
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'proof_notes' => $this->proof_notes,
            'proof_files' => $proofFiles,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
