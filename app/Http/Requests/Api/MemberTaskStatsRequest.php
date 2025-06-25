<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MemberTaskStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'member_id' => 'required|integer|exists:users,id'
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'معرف العضو مطلوب',
            'member_id.integer' => 'معرف العضو يجب أن يكون رقم',
            'member_id.exists' => 'العضو غير موجود'
        ];
    }
}