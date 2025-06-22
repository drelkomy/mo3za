<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamNameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:50'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الفريق مطلوب',
            'name.string' => 'اسم الفريق يجب أن يكون نصاً',
            'name.min' => 'اسم الفريق يجب أن يكون على الأقل 3 أحرف',
            'name.max' => 'اسم الفريق يجب أن لا يتجاوز 50 حرف'
        ];
    }
}