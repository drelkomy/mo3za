<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class ResetPasswordCustom extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 60;

    protected string $token;

    public function __construct(string $token)
    {
        $this->token = $token;

        Log::info('ResetPasswordCustom notification created', ['token' => $token]);
    }

    public function viaQueues(): array
    {
        return [
            'mail' => 'emails',
        ];
    }

    public function via(object $notifiable): array
    {
        Log::info('Sending password reset notification via mail');
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable->getEmailForPasswordReset();
        $uniqueId = uniqid('reset_', true);
        $cacheKey = "password_reset_{$uniqueId}_{$email}";
        \Illuminate\Support\Facades\Cache::put($cacheKey, ['used' => false, 'email' => $email], now()->addMinutes(config('auth.passwords.users.expire', 60)));

        $url = URL::temporarySignedRoute(
            'filament.admin.auth.password.reset',
            now()->addMinutes(config('auth.passwords.users.expire', 60)),
            [
                'token' => $this->token,
                'email' => $email,
                'unique_id' => $uniqueId,
            ],
            true
        );

        Log::info('Building password reset mail', [
            'url' => $url,
            'user_id' => $notifiable->id,
            'unique_id' => $uniqueId,
        ]);

        return (new MailMessage)
            ->subject('إعادة تعيين كلمة المرور - ' . config('app.name'))
            ->view('emails.password-reset', [
                'user' => $notifiable,
                'url' => $url,
                'token' => $this->token,
            ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ResetPasswordCustom notification failed', [
            'token' => $this->token,
            'error' => $exception->getMessage(),
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
