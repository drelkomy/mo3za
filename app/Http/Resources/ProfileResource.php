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
            'avatar_url' => $this->avatar_url ? asset('storage/' . $this->avatar_url) : null,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
            'gender' => $this->gender,
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'password' => $this->password,
        ];
    }
}
