<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:pending,in_progress,completed'
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'حالة المهمة مطلوبة',
            'status.string' => 'حالة المهمة يجب أن تكون نص',
            'status.in' => 'حالة المهمة يجب أن تكون إحدى القيم التالية: pending, in_progress, completed'
        ];
    }
}