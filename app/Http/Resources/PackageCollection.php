<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PackageCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        $packages = $this->collection->groupBy('is_trial');
        
        return [
            'trial_package' => $packages->get(1, collect())->first() ? 
                new PackageResource($packages->get(1)->first()) : null,
            'paid_packages' => PackageResource::collection($packages->get(0, collect())),
            'total_packages' => $this->collection->count(),
            'has_trial' => $packages->has(1),
        ];
    }
}