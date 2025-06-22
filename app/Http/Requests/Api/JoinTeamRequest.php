<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\JoinRequest;

class JoinTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'exists:users,email',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'يجب إدخال بريد إلكتروني صحيح.',
            'email.exists' => 'هذا البريد الإلكتروني غير مسجل في النظام.',
        ];
    }
}