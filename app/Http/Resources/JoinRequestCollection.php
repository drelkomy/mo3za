<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class JoinRequestCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        $total = $this->collection->count();
        
        if ($total === 0) {
            return [
                'data' => [],
                'meta' => ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0]
            ];
        }
        
        $statusCounts = $this->collection->countBy('status');
        
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $total,
                'pending' => $statusCounts->get('pending', 0),
                'accepted' => $statusCounts->get('accepted', 0),
                'rejected' => $statusCounts->get('rejected', 0),
            ]
        ];
    }
}