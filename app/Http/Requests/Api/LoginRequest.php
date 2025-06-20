<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرح له بإجراء هذا الطلب.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * الحصول على قواعد التحقق التي تنطبق على الطلب.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email',
            'password' => 'required|string|min:6',
        ];
    }

    /**
     * الحصول على رسائل الخطأ المخصصة للقواعد المحددة.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'يرجى إدخال بريد إلكتروني صالح',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'يجب أن تتكون كلمة المرور من 6 أحرف على الأقل',
        ];
    }
}