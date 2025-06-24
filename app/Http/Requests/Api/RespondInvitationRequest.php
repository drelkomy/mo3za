<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RespondInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'invitation_id' => 'required',
            'action' => 'required|in:accept,reject',
        ];
    }

    public function messages(): array
    {
        return [
            'invitation_id.required' => 'معرف الدعوة مطلوب',
            'action.required' => 'الإجراء مطلوب',
            'action.in' => 'الإجراء يجب أن يكون قبول أو رفض',
        ];
    }
}
