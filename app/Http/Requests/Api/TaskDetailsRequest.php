<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TaskDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id' => 'required|exists:tasks,id',
        ];
    }

    public function messages(): array
    {
        return [
            'task_id.required' => 'رقم المهمة مطلوب',
            'task_id.exists' => 'رقم المهمة غير صحيح',
        ];
    }
}