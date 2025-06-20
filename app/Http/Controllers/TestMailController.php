<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use Illuminate\Support\Facades\Log;

class TestMailController extends Controller
{
    public function testMail(Request $request)
    {
        try {
            // التحقق من وجود دعوة
            $invitation = Invitation::latest()->first();
            
            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد دعوات لاختبارها'
                ]);
            }
            
            // تسجيل محاولة الإرسال
            Log::info('Testing mail sending', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'mail_config' => [
                    'driver' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'from' => config('mail.from.address'),
                ]
            ]);
            
            // إرسال البريد مباشرة
            Mail::to($request->email ?? 'test@example.com')
                ->send(new InvitationMail($invitation));
            
            // تسجيل نجاح الإرسال
            Log::info('Test mail sent successfully');
            
            return response()->json([
                'success' => true,
                'message' => 'تم إرسال البريد بنجاح',
                'mail_config' => [
                    'driver' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'from' => config('mail.from.address'),
                ]
            ]);
            
        } catch (\Exception $e) {
            // تسجيل الخطأ
            Log::error('Test mail failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'فشل إرسال البريد',
                'error' => $e->getMessage(),
                'mail_config' => [
                    'driver' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'from' => config('mail.from.address'),
                ]
            ], 500);
        }
    }
    
    public function checkMailConfig()
    {
        return response()->json([
            'mail_config' => [
                'driver' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'encryption' => config('mail.mailers.smtp.encryption'),
                'username' => config('mail.mailers.smtp.username') ? 'مضبوط' : 'غير مضبوط',
                'password' => config('mail.mailers.smtp.password') ? 'مضبوط' : 'غير مضبوط',
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ]
        ]);
    }
}