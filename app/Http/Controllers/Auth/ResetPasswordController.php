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
        $cacheKey = "password_reset_used:{$token}";

        // Check if token has already been used
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return redirect()->route('filament.admin.auth.password.request')
                ->withErrors(['email' => ['تم استخدام هذا الرابط بالفعل. يرجى طلب رابط جديد.']]);
        }

        return view('auth.passwords.reset', ['token' => $token, 'email' => $request->email]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $token = $request->input('token');
        $cacheKey = "password_reset_used:{$token}";

        // Check if token has already been used
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return back()->withErrors(['email' => ['تم استخدام هذا الرابط بالفعل. يرجى طلب رابط جديد.']]);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($cacheKey) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                // Mark token as used in cache with expiration time (e.g., 1 day)
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addDay());

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
