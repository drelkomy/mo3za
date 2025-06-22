<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use App\Jobs\SendInvitationJob;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id', 'sender_id', 'email', 'token', 'status', 'accepted_at'
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // تحسين الأداء بإضافة العلاقات المستخدمة بشكل متكرر

    // إضافة نطاقات للاستعلامات المتكررة
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'accepted');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // دوال مساعدة لتحسين الأداء
    public function accept(): bool
    {
        return $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    public function reject(): bool
    {
        return $this->update(['status' => 'rejected']);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    // إنشاء دعوة جديدة مع إرسالها
    public static function createAndSend(int $teamId, int $senderId, string $email): self
    {
        // تسجيل بدء العملية
        Log::info('Creating invitation', [
            'team_id' => $teamId,
            'sender_id' => $senderId,
            'email' => $email,
        ]);

        $invitation = self::create([
            'team_id' => $teamId,
            'sender_id' => $senderId,
            'email' => $email,
            'token' => Str::random(32),
            'status' => 'pending',
        ]);

        // تسجيل إنشاء الدعوة
        Log::info('Invitation created', [
            'invitation_id' => $invitation->id,
            'token' => $invitation->token,
        ]);

        // إرسال الدعوة عبر الطابور
        SendInvitationJob::dispatch($invitation)->onQueue('emails');

        return $invitation;
    }

    // اختبار إرسال الدعوة بدون طابور (للاختبار فقط)
    public function sendWithoutQueue(): void
    {
        // تسجيل في السجل
        Log::info('Sending invitation without queue', [
            'invitation_id' => $this->id,
            'email' => $this->email,
            'team' => $this->team->name,
            'sender' => $this->sender->name,
        ]);

        // إرسال الإيميل مباشرة
        \Illuminate\Support\Facades\Mail::to($this->email)
            ->send(new \App\Mail\InvitationMail($this));
    }
}