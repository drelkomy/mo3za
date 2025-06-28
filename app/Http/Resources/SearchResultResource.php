<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tasks' => $this->when(isset($this->resource['tasks']), 
                TaskResource::collection($this->resource['tasks'] ?? [])
            ),
            'stages' => $this->when(isset($this->resource['stages']), 
                TaskStageResource::collection($this->resource['stages'] ?? [])
            ),
            'members' => $this->when(isset($this->resource['members']), 
                UserResource::collection($this->resource['members'] ?? [])
            ),
            'total_results' => $this->resource['total_results'] ?? 0,
        ];
    }
}