<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'receiver_id' => ['required_without:selected_members', 'nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date', 'after:today'],
            'priority' => ['nullable', 'string', 'in:urgent,normal,high,medium,low'],
            'total_stages' => ['nullable', 'integer', 'min:1', 'max:10'],
            'stages' => ['nullable', 'array'],
            'stages.*.title' => ['required_with:stages', 'string', 'max:255'],
            'stages.*.description' => ['nullable', 'string'],
            'reward_amount' => ['nullable', 'numeric', 'min:0'],
            'reward_type' => ['nullable', 'string', 'in:cash,other'],
            'reward_description' => ['nullable', 'string'],
            'selected_members' => ['nullable', 'sometimes', 'required_without:receiver_id'],
            'selected_members.*' => ['integer', 'exists:users,id'],
        ];
    }
    
    public function messages(): array
    {
        return [
            'title.required' => 'عنوان المهمة مطلوب',
            'description.required' => 'وصف المهمة مطلوب',
            'receiver_id.required_without' => 'يجب تحديد مستلم أو مجموعة مستلمين',
            'receiver_id.exists' => 'المستلم غير موجود',
            'priority.in' => 'الأولوية يجب أن تكون إما normal، urgent، high، medium أو low',
            'due_date.after' => 'تاريخ الاستحقاق يجب أن يكون بعد اليوم',
            'total_stages.max' => ' لقد تخطيت عدد المراحل بالياقة',
            'reward_amount.min' => 'قيمة المكافأة يجب أن تكون أكبر من أو تساوي صفر',
            'reward_type.in' => 'نوع المكافأة يجب أن يكون إما cash أو other',
            'selected_members.required_without' => 'يجب تحديد مستلم أو مجموعة مستلمين',
            'selected_members.*.exists' => 'أحد الأعضاء المحددين غير موجود',
        ];
    }
}
