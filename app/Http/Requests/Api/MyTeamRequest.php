<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MyTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'page' => 'integer|min:1|max:1000',
            'per_page' => 'integer|min:1|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'page.integer' => 'رقم الصفحة يجب أن يكون رقماً صحيحاً',
            'page.min' => 'رقم الصفحة يجب أن يكون أكبر من 0',
            'page.max' => 'رقم الصفحة لا يمكن أن يتجاوز 1000',
            'per_page.integer' => 'عدد العناصر في الصفحة يجب أن يكون رقماً صحيحاً',
            'per_page.min' => 'عدد العناصر في الصفحة يجب أن يكون أكبر من 0',
            'per_page.max' => 'عدد العناصر في الصفحة لا يمكن أن يتجاوز 50',
        ];
    }
}