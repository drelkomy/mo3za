<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $this->user()->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'avatar_url' => 'sometimes|nullable|string',
            'gender' => 'sometimes|nullable|in:male,female',
            'birthdate' => 'sometimes|nullable|date',
            'password' => 'sometimes|nullable|string|min:8',
            'city_id' => 'sometimes|nullable|integer|exists:cities,id',
            'area_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('areas', 'id')->where(function ($query) {
                    // Only validate if city_id is provided
                    if ($this->input('city_id')) {
                        return $query->where('city_id', $this->input('city_id'));
                    }
                    return $query;
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'يجب أن يكون الاسم نصًا.',
            'name.max' => 'يجب ألا يتجاوز الاسم 255 حرفًا.',
            'email.email' => 'يجب أن يكون البريد الإلكتروني عنوان بريد إلكتروني صالحًا.',
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل.',
            'phone.string' => 'يجب أن يكون رقم الهاتف نصًا.',
            'phone.max' => 'يجب ألا يتجاوز رقم الهاتف 20 حرفًا.',
            'gender.in' => 'يجب أن يكون الجنس ذكرًا أو أنثى.',
            'birthdate.date' => 'يجب أن يكون تاريخ الميلاد تاريخًا صالحًا.',
            'password.min' => 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.',
            'area_id.integer' => 'يجب أن تكون المنطقة رقمًا صحيحًا.',
            'area_id.exists' => 'المنطقة المحددة غير صالحة أو لا تنتمي للمدينة المختارة.',
            'city_id.integer' => 'يجب أن تكون المدينة رقمًا صحيحًا.',
            'city_id.exists' => 'المدينة المحددة غير صالحة.',
        ];
    }
}
