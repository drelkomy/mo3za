<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Package;

class SubscribePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'package_id' => [
                'required',
                'integer',
                'exists:packages,id',
                function ($attribute, $value, $fail) {
                    $package = Package::find($value);
                    if (!$package || !$package->is_active) {
                        $fail('الباقة غير متاحة للاشتراك.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'package_id.required' => 'معرف الباقة مطلوب.',
            'package_id.integer' => 'معرف الباقة يجب أن يكون رقم.',
            'package_id.exists' => 'الباقة غير موجودة.',
        ];
    }
}