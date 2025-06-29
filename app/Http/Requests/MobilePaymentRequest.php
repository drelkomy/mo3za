<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MobilePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => 'required|exists:packages,id'
        ];
    }
}