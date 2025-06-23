<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'package_id' => 'required|exists:packages,id',
        ];
    }

    public function messages(): array
    {
        return [
            'package_id.required' => 'يجب اختيار الباقة',
            'package_id.exists' => 'الباقة المحددة غير موجودة',
        ];
    }
}