<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'package_id', 'team_id', 'status', 'payment_id', 'price_paid',
        'tasks_created', 'max_tasks',
        'max_milestones_per_task', 'previous_tasks_completed', 'previous_tasks_pending',
        'previous_rewards_amount', 'start_date', 'end_date'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'tasks_created' => 'integer',

        'max_tasks' => 'integer',

        'max_milestones_per_task' => 'integer',
        'price_paid' => 'decimal:2',
        'previous_tasks_completed' => 'integer',
        'previous_tasks_pending' => 'integer',
        'previous_rewards_amount' => 'decimal:2',
    ];

   

    // إضافة نطاق للاشتراكات النشطة لتحسين الاستعلامات المتكررة
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // إضافة نطاق للاشتراكات المنتهية لتحسين الاستعلامات المتكررة
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', '!=', 'active');
    }



    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // Get active subscription for user (static method for User model)
    public static function getActiveSubscription(User $user): ?Subscription
    {
        return static::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->first();
    }
    
    public function canCreateTask(): bool
    {
        // تحسين الأداء بتجنب الاستعلامات
        return $this->status === 'active' && $this->tasks_created < $this->max_tasks;
    }
    
    public function canAddMember(): bool
    {
        return $this->status === 'active';
    }

    public function incrementTasksCreated(): bool
    {
        if ($this->canCreateTask()) {
            // استخدام increment بدلاً من update لتحسين الأداء
            return $this->increment('tasks_created') ? true : false;
        }
        return false;
    }

    public function isExpired(): bool
    {
        if ($this->status !== 'active') {
            return true;
        }

        if ($this->max_tasks > 0 && $this->tasks_created >= $this->max_tasks) {
            return true;
        }

        return false;
    }
}