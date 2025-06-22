<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;

class TestPasswordResetController extends Controller
{
    public function sendTestReset(Request $request)
    {
        $email = $request->input('email', 'test@example.com');
        
        try {
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 404);
            }

            // Generate token and send notification directly
            $token = Password::createToken($user);
            $user->sendPasswordResetNotification($token);
            
            Log::info('Test password reset sent', [
                'email' => $email,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال رابط استعادة كلمة المرور بنجاح',
                'email' => $email
            ]);

        } catch (\Exception $e) {
            Log::error('Test password reset failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في إرسال الإيميل: ' . $e->getMessage()
            ], 500);
        }
    }
}