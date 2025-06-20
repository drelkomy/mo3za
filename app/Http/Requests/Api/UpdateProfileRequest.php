<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $this->user()->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'avatar_url' => 'sometimes|nullable|string',
            'gender' => 'sometimes|nullable|in:male,female',
            'birthdate' => 'sometimes|nullable|date',
        ];
    }
}
