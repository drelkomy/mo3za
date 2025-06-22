<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

class CustomRequestPasswordReset extends RequestPasswordReset
{
    public function request(): void
    {
        $data = $this->form->getState();
        $email = $data['email'];
        
        // Rate limiting
        $key = 'password-reset:' . $email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            Notification::make()
                ->title("تم تجاوز الحد المسموح. حاول مرة أخرى بعد {$seconds} ثانية")
                ->danger()
                ->send();
            return;
        }

        RateLimiter::hit($key, 300); // 5 minutes

        // Check cache
        $cacheKey = "reset-sent:{$email}";
        if (Cache::has($cacheKey)) {
            Notification::make()
                ->title('تم إرسال رابط الاستعادة مؤخراً')
                ->body('انتظر دقيقتين قبل المحاولة مرة أخرى')
                ->warning()
                ->send();
            return;
        }

        $user = User::where('email', $email)->first();
        
        if (!$user) {
            Notification::make()
                ->title('البريد الإلكتروني غير موجود')
                ->danger()
                ->send();
            return;
        }

        // Generate token and queue notification
        $token = Password::createToken($user);
        $user->sendPasswordResetNotification($token);
        
        // Cache to prevent spam
        Cache::put($cacheKey, true, 120); // 2 minutes

        Notification::make()
            ->title('تم إضافة طلبك للمعالجة')
            ->body('سيتم إرسال رابط الاستعادة خلال دقائق قليلة')
            ->success()
            ->send();

        $this->form->fill();
    }
}