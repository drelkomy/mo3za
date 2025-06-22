<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordResetJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'البيانات غير صحيحة',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $key = 'password-reset:' . $email;

        // Rate limiting - 3 requests per 5 minutes
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "تم تجاوز الحد المسموح. حاول مرة أخرى بعد {$seconds} ثانية"
            ], 429);
        }

        RateLimiter::hit($key, 300); // 5 minutes

        // Check if reset was sent recently (cache for 2 minutes)
        $cacheKey = "reset-sent:{$email}";
        if (Cache::has($cacheKey)) {
            return response()->json([
                'success' => false,
                'message' => 'تم إرسال رابط الاستعادة مؤخراً. انتظر دقيقتين قبل المحاولة مرة أخرى'
            ], 429);
        }

        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'البريد الإلكتروني غير موجود'
            ], 404);
        }

        // Generate token and queue notification
        $token = Password::createToken($user);
        $user->sendPasswordResetNotification($token);
        
        // Cache to prevent spam
        Cache::put($cacheKey, true, 120); // 2 minutes

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة طلبك للمعالجة. سيتم إرسال رابط الاستعادة خلال دقائق قليلة'
        ]);
    }
}