<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $dates = [
        'start_date',
        'end_date',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'user_id',
        'package_id',
        'status',
        'payment_id',
        'price_paid',
        'tasks_created',
        'participants_created',
        'max_tasks',
        'max_participants',
        'max_milestones_per_task',
        'previous_tasks_completed',
        'previous_tasks_pending',
        'previous_rewards_amount',
        'start_date',
        'end_date',
    ];



    /**
     * Get the user that owns the subscription.
     * المستخدم الذي يملك هذا الاشتراك
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the package that the subscription belongs to.
     * الباقة التي ينتمي إليها هذا الاشتراك
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the payment that created this subscription
     * عملية الدفع التي أنشأت هذا الاشتراك
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the duration of the subscription in days
     *
     * @return int
     */
    public function getDurationInDays(): int
    {
        if (!$this->end_date || !$this->start_date) {
            return 0;
        }
        return $this->end_date->diffInDays($this->start_date);
    }

    /**
     * Get the remaining days of the subscription
     *
     * @return int
     */
    public function getRemainingDays(): int
    {
        if (!$this->end_date) {
            return 0;
        }
        return $this->end_date->diffInDays(now());
    }

    /**
     * Check if subscription is expired
     * الاشتراك ينتهي عند استنفاذ عدد المهام أو المشاركين أو المراحل وليس بالوقت
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        // إذا كانت حالة الاشتراك غير نشطة، فهو منتهي
        if ($this->status !== 'active') {
            return true;
        }
        
        // التحقق من استنفاذ عدد المهام
        if ($this->tasks_created >= $this->max_tasks) {
            return true;
        }
        
        // التحقق من استنفاذ عدد المشاركين
        if ($this->participants_created >= $this->max_participants) {
            return true;
        }
        
        // الاشتراك لا يزال صالحاً
        return false;
    }

    /**
     * Check if user can add more tasks
     * التحقق من إمكانية إضافة مهام جديدة
     *
     * @return bool
     */
    public function canAddTasks(): bool
    {
        return $this->status === 'active' && $this->tasks_created < $this->max_tasks;
    }

    /**
     * Check if user can add more participants
     * التحقق من إمكانية إضافة مشاركين جدد
     *
     * @return bool
     */
    public function canAddParticipants(): bool
    {
        return $this->status === 'active' && $this->participants_created < $this->max_participants;
    }

    /**
     * Check if user can add more milestones to a task
     * التحقق من إمكانية إضافة مراحل جديدة للمهمة
     *
     * @return bool
     */
    public function canAddMilestones(): bool
    {
        return $this->status === 'active' && $this->max_milestones_per_task > 0;
    }

    /**
     * Increment tasks created count
     * زيادة عدد المهام المنشأة
     *
     * @return bool
     */
    public function incrementTasksCreated(): bool
    {
        if ($this->canAddTasks()) {
            $this->tasks_created += 1;
            return $this->save();
        }
        return false;
    }

    /**
     * Increment participants created count
     * زيادة عدد المشاركين المنشأين
     *
     * @return bool
     */
    public function incrementParticipantsCreated(): bool
    {
        if ($this->canAddParticipants()) {
            $this->participants_created += 1;
            return $this->save();
        }
        return false;
    }
}