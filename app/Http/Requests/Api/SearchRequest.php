<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => 'required|string|min:1|max:100',
            'type' => 'nullable|string|in:task,stage,member,all',
        ];
    }

    public function messages(): array
    {
        return [
            'query.required' => 'نص البحث مطلوب',
            'query.min' => 'نص البحث يجب أن يكون على الأقل حرف واحد',
            'query.max' => 'نص البحث لا يجب أن يتجاوز 100 حرف',
            'type.in' => 'نوع البحث يجب أن يكون: task أو stage أو member أو all',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => 'بيانات غير صحيحة',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}