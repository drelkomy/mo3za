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

class SendInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Invitation $invitation) {}

    public function handle(): void
    {
        // إرسال إيميل الدعوة
        Mail::to($this->invitation->email)->send(new InvitationMail($this->invitation));
        
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
                route('invitations.show', $this->invitation->token)
            );
        }
    }
}