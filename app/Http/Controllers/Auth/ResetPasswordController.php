<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function showResetForm(Request $request, $token)
    {
        $email = $request->email;
        $uniqueId = $request->unique_id;
        $cacheKey = "password_reset_{$uniqueId}_{$email}";

        // Check if token has already been used or invalid
        if (!\Illuminate\Support\Facades\Cache::has($cacheKey) || \Illuminate\Support\Facades\Cache::get($cacheKey)['used']) {
            return redirect()->route('filament.admin.auth.password.request')
                ->withErrors(['email' => ['تم استخدام هذا الرابط بالفعل أو انتهت صلاحيته. يرجى طلب رابط جديد.']]);
        }

        return view('auth.passwords.reset', ['token' => $token, 'email' => $request->email]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
            'unique_id' => 'required',
        ]);

        $email = $request->input('email');
        $uniqueId = $request->input('unique_id');
        $cacheKey = "password_reset_{$uniqueId}_{$email}";

        // Check if token has already been used or invalid
        if (!\Illuminate\Support\Facades\Cache::has($cacheKey) || \Illuminate\Support\Facades\Cache::get($cacheKey)['used']) {
            return back()->withErrors(['email' => ['تم استخدام هذا الرابط بالفعل أو انتهت صلاحيته. يرجى طلب رابط جديد.']]);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($cacheKey) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                // Mark token as used in cache
                $cacheData = \Illuminate\Support\Facades\Cache::get($cacheKey);
                $cacheData['used'] = true;
                \Illuminate\Support\Facades\Cache::put($cacheKey, $cacheData, now()->addDay());

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            // Ensure the cache entry is marked as used immediately after successful reset
            $cacheData = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if ($cacheData) {
                $cacheData['used'] = true;
                \Illuminate\Support\Facades\Cache::put($cacheKey, $cacheData, now()->addDay());
            }
            return redirect()->route('password.success')->with('status', 'تم تغيير كلمة المرور بنجاح. سيتم توجيهك إلى الصفحة الرئيسية تلقائيًا.');
        } else {
            return back()->withErrors(['email' => [__($status)]]);
        }
    }
}
