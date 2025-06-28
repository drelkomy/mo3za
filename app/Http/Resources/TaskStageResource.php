<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskStageResource extends JsonResource
{
    public function toArray($request)
    {
        $proofFiles = null;
        if ($this->proof_files && is_array($this->proof_files) && isset($this->proof_files['path'])) {
            $proofFiles = [
                'path' => $this->proof_files['path'],
                'url' => $this->proof_files['url'] ?? asset('storage/' . $this->proof_files['path']),
                'name' => $this->proof_files['name'] ?? null,
                'size' => $this->proof_files['size'] ?? null,
                'type' => $this->proof_files['type'] ?? null,
                'uploaded_at' => $this->proof_files['uploaded_at'] ?? null
            ];
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
