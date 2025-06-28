<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StageResource extends JsonResource
{
    public function toArray(Request $request): array
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
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'stage_number' => $this->stage_number,
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'proof_notes' => $this->proof_notes,
            'proof_files' => $proofFiles,
        ];
    }
}