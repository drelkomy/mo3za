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
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date|after_or_equal:today',
            'duration_days' => 'required|integer|min:1|max:365',
            'total_stages' => 'required|integer|min:1|max:20',
            'reward_type' => 'required|in:cash,other',
            'reward_amount' => 'required_if:reward_type,cash|nullable|numeric|min:0',
            'reward_description' => 'required_if:reward_type,other|nullable|string|max:500',
            'is_multiple' => 'boolean',
            'assigned_members' => 'required_if:is_multiple,true|array|min:1',
            'assigned_members.*' => 'exists:users,id',
            'assigned_to' => 'required_if:is_multiple,false|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان المهمة مطلوب',
            'description.required' => 'وصف المهمة مطلوب',
            'start_date.required' => 'تاريخ البداية مطلوب',
            'start_date.after_or_equal' => 'تاريخ البداية يجب أن يكون اليوم أو بعده',
            'duration_days.required' => 'المدة الزمنية مطلوبة',
            'duration_days.min' => 'المدة الزمنية يجب أن تكون يوم واحد على الأقل',
            'total_stages.required' => 'عدد المراحل مطلوب',
            'total_stages.min' => 'يجب أن يكون هناك مرحلة واحدة على الأقل',
            'reward_type.required' => 'نوع المكافأة مطلوب',
            'reward_type.in' => 'نوع المكافأة يجب أن يكون نقدي أو آخر',
            'reward_amount.required_if' => 'مبلغ المكافأة مطلوب للمكافآت النقدية',
            'reward_description.required_if' => 'وصف المكافأة مطلوب للمكافآت غير النقدية',
            'assigned_members.required_if' => 'يجب تحديد الأعضاء للمهمة المتعددة',
            'assigned_to.required_if' => 'يجب تحديد المكلف بالمهمة',
        ];
    }
}