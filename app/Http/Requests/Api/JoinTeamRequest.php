<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\JoinRequest;

class JoinTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'exists:users,email',
                function ($attribute, $value, $fail) {
                    // التحقق من عدم إرسال طلب لنفسه
                    if ($value === auth()->user()->email) {
                        $fail('لا يمكنك إرسال طلب انضمام لنفسك.');
                    }
                    
                    $targetUser = User::where('email', $value)->first();
                    if ($targetUser) {
                        // التحقق من وجود فريق
                        if (!$targetUser->ownedTeams()->exists()) {
                            $fail('هذا المستخدم لا يملك فريقاً.');
                        }
                        
                        $team = $targetUser->ownedTeams()->first();
                        if ($team) {
                            // التحقق من عدم وجود طلب سابق
                            if (JoinRequest::where('user_id', auth()->id())->where('team_id', $team->id)->where('status', 'pending')->exists()) {
                                $fail('لديك طلب انضمام معلق بالفعل.');
                            }
                            
                            // التحقق من عدم كونه عضو بالفعل
                            if ($team->members()->where('user_id', auth()->id())->exists()) {
                                $fail('أنت عضو في هذا الفريق بالفعل.');
                            }
                        }
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'يجب إدخال بريد إلكتروني صحيح.',
            'email.exists' => 'هذا البريد الإلكتروني غير مسجل في النظام.',
        ];
    }
}