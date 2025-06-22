<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ResetPasswordCustom extends Notification implements ShouldQueue
{
    use Queueable;
    
    public $tries = 3;
    public $timeout = 60;

    public $token;

    /**
     * Create a new notification instance.
     */
    public function __construct($token)
    {
        $this->token = $token;
        $this->onQueue('emails');
        Log::info('ResetPasswordCustom notification created', ['token' => $token]);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        Log::info('Sending password reset notification via mail');
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('filament.admin.auth.password-reset.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
        
        Log::info('Building password reset mail', ['url' => $url, 'user_id' => $notifiable->id]);
        
        return (new MailMessage)
            ->subject('إعادة تعيين كلمة المرور - ' . config('app.name'))
            ->view('emails.password-reset', [
                'user' => $notifiable,
                'url' => $url,
                'token' => $this->token
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
