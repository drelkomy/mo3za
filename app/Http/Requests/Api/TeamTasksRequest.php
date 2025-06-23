<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TeamTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:pending,in_progress,completed,cancelled'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}