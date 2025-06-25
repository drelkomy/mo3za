<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ResetPasswordCustom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPasswordResetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    protected $user;
    protected $token;

    public function __construct(User $user, string $token)
    {
        $this->user = $user;
        $this->token = $token;
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        try {
            // تحقق من أن البريد الإلكتروني مكتوب بشكل صحيح
            if (!filter_var($this->user->email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Invalid email format - skipping', [
                    'user_id' => $this->user->id,
                    'email' => $this->user->email
                ]);
                return; // لا ترسل بريد لمستخدم ببريد خاطئ
            }

            $this->user->notify(new ResetPasswordCustom($this->token));

            Log::info('Password reset email sent successfully', [
                'user_id' => $this->user->id,
                'email' => $this->user->email
            ]);
        } catch (\Swift_TransportException $e) {
            // خطأ دائم (مثل البريد غير موجود أو السيرفر رفض الرسالة)
            Log::error('SMTP transport error - job failed and will not retry', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage()
            ]);

            // اختياري: حفظ البريد المرفوض في جدول خاص لتجاهله لاحقًا
            // InvalidEmail::create(['email' => $this->user->email]);

            $this->fail($e); // منع التكرار
        } catch (\Exception $e) {
            // أخطاء أخرى: يمكن إعادة المحاولة
            Log::error('Failed to send password reset email', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Password reset job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage()
        ]);
    }
}
