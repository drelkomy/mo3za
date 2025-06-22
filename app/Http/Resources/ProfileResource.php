<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url ? \Illuminate\Support\Facades\Storage::url($this->avatar_url) : null,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
            'gender' => $this->gender,
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'area' => $this->whenLoaded('area', function () {
                return $this->area ? [
                    'id' => $this->area->id,
                    'name' => $this->area->name,
                ] : null;
            }),
            'city' => $this->whenLoaded('city', function () {
                return $this->city ? [
                    'id' => $this->city->id,
                    'name' => $this->city->name,
                ] : null;
            }),
            'active_subscription' => $this->whenLoaded('subscriptions', function () {
                $activeSubscription = $this->subscriptions->where('status', 'active')->first();

                if (!$activeSubscription) {
                    return null;
                }

                return [
                    'package_name' => optional($activeSubscription->package)->name,
                    'status' => $activeSubscription->status,
                    'tasks_remaining' => $activeSubscription->max_tasks - $activeSubscription->tasks_created,

                ];
            }),
        ];
    }
}
