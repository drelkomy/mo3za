<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskDueDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'task_id' => 'required|integer|exists:tasks,id',
            'due_date' => 'required|date|after:today'
        ];
    }

    public function messages(): array
    {
        return [
            'task_id.required' => 'رقم المهمة مطلوب',
            'task_id.exists' => 'المهمة غير موجودة',
            'due_date.required' => 'تاريخ الانتهاء مطلوب',
            'due_date.date' => 'تاريخ الانتهاء غير صحيح',
            'due_date.after' => 'تاريخ الانتهاء يجب أن يكون في المستقبل'
        ];
    }
}