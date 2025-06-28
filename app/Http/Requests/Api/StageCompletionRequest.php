<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StageCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'stage_id' => 'required|integer|exists:task_stages,id',
            'proof_notes' => 'nullable|string|max:1000',
            'proof_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ];
    }

    public function messages(): array
    {
        return [
            'stage_id.required' => 'رقم المرحلة مطلوب',
            'stage_id.exists' => 'المرحلة غير موجودة',
            'proof_notes.max' => 'الملاحظات يجب ألا تتجاوز 1000 حرف',
            'proof_image.image' => 'يجب أن يكون الملف صورة',
            'proof_image.mimes' => 'نوع الصورة غير مدعوم',
            'proof_image.max' => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت'
        ];
    }
}