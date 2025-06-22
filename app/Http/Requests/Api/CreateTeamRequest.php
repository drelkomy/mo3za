<?php

namespace App\Http\Requests\Api;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;

class CreateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        // التحقق من أن المستخدم لا يملك فريق بالفعل
        $hasTeam = Team::where('owner_id', auth()->id())->exists();
        
        return auth()->check() && !$hasTeam;
    }

    public function rules(): array
    {
        // تحديد عدد الطلبات - لا يمكن إنشاء أكثر من فريق واحد كل 24 ساعة
        $key = 'create_team:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            RateLimiter::hit($key, 60 * 24); // 24 ساعة
            $this->failedAuthorization();
        }
        
        RateLimiter::hit($key, 60 * 24); // 24 ساعة
        
        return [
            'name' => 'required|string|min:3|max:50'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الفريق مطلوب',
            'name.string' => 'اسم الفريق يجب أن يكون نصاً',
            'name.min' => 'اسم الفريق يجب أن يكون على الأقل 3 أحرف',
            'name.max' => 'اسم الفريق يجب أن لا يتجاوز 50 حرف'
        ];
    }
    
    public function failedAuthorization()
    {
        throw new \Illuminate\Auth\Access\AuthorizationException('لا يمكنك إنشاء أكثر من فريق واحد');
    }
}