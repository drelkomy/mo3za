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
            'proof_image' => 'nullable|file|image|mimes:jpg,jpeg,png|max:5120', // 5MB max, only images
        ];
    }

    public function messages(): array
    {
        return [
            'stage_id.required' => 'معرف المرحلة مطلوب',
            'stage_id.exists' => 'المرحلة غير موجودة',
            'proof_notes.required' => 'ملاحظات الإثبات مطلوبة',
            'proof_notes.max' => 'ملاحظات الإثبات يجب ألا تتجاوز 1000 حرف',
            'proof_image.image' => 'يجب أن يكون الملف صورة',
            'proof_image.mimes' => 'يجب أن تكون الصورة من نوع jpg أو jpeg أو png',
            'proof_image.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت',
        ];
    }
}