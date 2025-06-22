<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RemoveMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'member_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value == auth()->id()) {
                        $fail('لا يمكنك إزالة نفسك من الفريق.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'معرف العضو مطلوب.',
            'member_id.integer' => 'معرف العضو يجب أن يكون رقم.',
            'member_id.exists' => 'العضو غير موجود.',
        ];
    }
}