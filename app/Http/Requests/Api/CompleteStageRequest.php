<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CompleteStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'stage_id' => 'required|exists:task_stages,id',
            'proof_notes' => 'required|string|max:1000',
            'proof_files' => 'nullable|array|max:5',
            'proof_files.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240', // 10MB
        ];
    }

    public function messages(): array
    {
        return [
            'stage_id.required' => 'معرف المرحلة مطلوب',
            'stage_id.exists' => 'المرحلة غير موجودة',
            'proof_notes.required' => 'ملاحظات الإثبات مطلوبة',
            'proof_notes.max' => 'ملاحظات الإثبات يجب ألا تتجاوز 1000 حرف',
            'proof_files.max' => 'لا يمكن رفع أكثر من 5 ملفات',
            'proof_files.*.mimes' => 'نوع الملف غير مدعوم',
            'proof_files.*.max' => 'حجم الملف يجب ألا يتجاوز 10 ميجابايت',
        ];
    }
}