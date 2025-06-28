<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TaskClosureRequest extends FormRequest
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
            'proof_notes' => 'nullable|string|max:1000',
            'proof_image' => 'nullable|file|image|mimes:jpg,jpeg,png|max:5120', // 5MB max, only images
        ];
    }

    public function messages(): array
    {
        return [
            'task_id.required' => 'معرف المهمة مطلوب',
            'task_id.exists' => 'المهمة غير موجودة',
            'status.required' => 'حالة الإغلاق مطلوبة',
            'status.in' => 'حالة الإغلاق يجب أن تكون تم الإنجاز أو لم يتم الإنجاز',
            'proof_notes.max' => 'ملاحظات الإثبات يجب ألا تتجاوز 1000 حرف',
            'proof_image.image' => 'يجب أن يكون الملف صورة',
            'proof_image.mimes' => 'يجب أن تكون الصورة من نوع jpg أو jpeg أو png',
            'proof_image.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت',
        ];
    }
}