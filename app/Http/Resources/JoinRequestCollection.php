<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class JoinRequestCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->collection->count(),
                'pending' => $this->collection->where('status', 'pending')->count(),
                'accepted' => $this->collection->where('status', 'accepted')->count(),
                'rejected' => $this->collection->where('status', 'rejected')->count(),
            ]
        ];
    }
}