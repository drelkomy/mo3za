<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id', 'giver_id', 'receiver_id', 'amount', 'status', 'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

  

    // إضافة فهارس للبحث السريع
    protected static function boot()
    {
        parent::boot();
        
        // ترتيب المكافآت افتراضياً من الأحدث للأقدم
        static::addGlobalScope('ordered', function ($query) {
            $query->latest();
        });
    }

    // نطاقات الاستعلام المتكررة
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function($q) use ($userId) {
            $q->where('giver_id', $userId)
              ->orWhere('receiver_id', $userId);
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'giver_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // دوال مساعدة لتحسين الأداء
    public function approve(): bool
    {
        return $this->update(['status' => 'completed']);
    }

    public function reject(): bool
    {
        return $this->update(['status' => 'rejected']);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}