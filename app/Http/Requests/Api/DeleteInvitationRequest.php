<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DeleteInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'invitation_id' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'invitation_id.required' => 'معرف الدعوة مطلوب',
        ];
    }
}
