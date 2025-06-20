<?php

namespace App\Jobs;

use App\Models\Invitation;
use App\Models\User;
use App\Mail\InvitationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // عدد محاولات إعادة المحاولة
    public $tries = 3;

    // الوقت بين المحاولات (بالثواني)
    public $backoff = 60;

    public function __construct(public Invitation $invitation) 
    {
        // تسجيل بدء العملية
        Log::info('Invitation job created', [
            'invitation_id' => $invitation->id,
            'email' => $invitation->email,
            'team' => $invitation->team->name,
        ]);
    }

    public function handle(): void
    {
        // تسجيل بدء المعالجة
        Log::info('Processing invitation job', [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
        ]);

        try {
            // إرسال إيميل الدعوة
            Mail::to($this->invitation->email)->send(new InvitationMail($this->invitation));
            
            // تسجيل نجاح الإرسال
            Log::info('Invitation email sent successfully', [
                'invitation_id' => $this->invitation->id,
                'email' => $this->invitation->email,
            ]);
            
            // إرسال إشعار للمرسل
            SendNotificationJob::dispatch(
                $this->invitation->sender,
                'تم إرسال الدعوة',
                "تم إرسال دعوة انضمام للفريق {$this->invitation->team->name} إلى {$this->invitation->email}",
                'success'
            );

            // إرسال إشعار للمستقبل إذا كان لديه حساب
            $existingUser = User::where('email', $this->invitation->email)->first();
            if ($existingUser) {
                SendNotificationJob::dispatch(
                    $existingUser,
                    'دعوة انضمام للفريق',
                    "تم دعوتك للانضمام إلى فريق {$this->invitation->team->name}",
                    'info',
                    ['invitation_id' => $this->invitation->id],
                    url('/invitations/' . $this->invitation->token)
                );
            }
        } catch (\Exception $e) {
            // تسجيل الخطأ
            Log::error('Failed to send invitation email', [
                'invitation_id' => $this->invitation->id,
                'email' => $this->invitation->email,
                'error' => $e->getMessage(),
            ]);
            
            // إعادة رمي الاستثناء للسماح بإعادة المحاولة
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // تسجيل فشل المهمة
        Log::error('Invitation job failed', [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'error' => $exception->getMessage(),
        ]);
        
        // إرسال إشعار للمرسل بفشل الإرسال
        SendNotificationJob::dispatch(
            $this->invitation->sender,
            'فشل إرسال الدعوة',
            "فشل إرسال دعوة الانضمام إلى {$this->invitation->email}. الرجاء المحاولة مرة أخرى.",
            'error'
        );
    }
}