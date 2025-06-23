<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CloseTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'task_id' => 'required|exists:tasks,id',
            'status' => 'required|in:completed,not_completed',
        ];
    }

    public function messages(): array
    {
        return [
            'task_id.required' => 'معرف المهمة مطلوب',
            'task_id.exists' => 'المهمة غير موجودة',
            'status.required' => 'حالة الإغلاق مطلوبة',
            'status.in' => 'حالة الإغلاق يجب أن تكون تم الإنجاز أو لم يتم الإنجاز',
        ];
    }
}