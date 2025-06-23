<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
            'message' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'email.exists' => 'المستخدم غير موجود',
            'message.max' => 'الرسالة يجب ألا تتجاوز 500 حرف',
        ];
    }
}