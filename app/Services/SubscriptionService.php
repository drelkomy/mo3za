<?php

namespace App\Services;

use App\Models\User;
use App\Models\Task;
use App\Models\Team;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Check if user can create a new task
     * التحقق من إمكانية إنشاء مهمة جديدة
     *
     * @param User $user
     * @return bool
     */
    public function canCreateTask(User $user): bool
    {
        return $user->canAddTasks();
    }
    
    /**
     * Check if user can add a team member
     * التحقق من إمكانية إضافة عضو للفريق
     *
     * @param User $user
     * @return bool
     */
    public function canAddTeamMember(User $user): bool
    {
        return $user->canAddParticipants();
    }
    
    /**
     * Check if user can add milestones to a task
     * التحقق من إمكانية إضافة مراحل للمهمة
     *
     * @param User $user
     * @param Task $task
     * @return bool
     */
    public function canAddMilestones(User $user, Task $task): bool
    {
        $subscription = $user->activeSubscription();
        if (!$subscription) {
            return false;
        }
        
        // التحقق من عدد المراحل الحالية للمهمة
        $currentMilestones = $task->stages()->count();
        return $currentMilestones < $subscription->max_milestones_per_task;
    }
    
    /**
     * Process task creation and update subscription counters
     * معالجة إنشاء مهمة جديدة وتحديث عدادات الاشتراك
     *
     * @param User $user
     * @param Task $task
     * @return bool
     */
    public function processTaskCreation(User $user, Task $task): bool
    {
        if (!$this->canCreateTask($user)) {
            return false;
        }
        
        // زيادة عداد المهام المنشأة
        $result = $user->incrementTasksCreated();
        
        if ($result) {
            Log::info('Task created and subscription counter updated', [
                'user_id' => $user->id,
                'task_id' => $task->id,
                'subscription_id' => $user->activeSubscription()->id ?? null
            ]);
        }
        
        return $result;
    }
    
    /**
     * Process team member addition and update subscription counters
     * معالجة إضافة عضو للفريق وتحديث عدادات الاشتراك
     *
     * @param User $user
     * @param Team $team
     * @param User $member
     * @return bool
     */
    public function processTeamMemberAddition(User $user, Team $team, User $member): bool
    {
        if (!$this->canAddTeamMember($user)) {
            return false;
        }
        
        // زيادة عداد المشاركين المنشأين
        $result = $user->incrementParticipantsCreated();
        
        if ($result) {
            Log::info('Team member added and subscription counter updated', [
                'user_id' => $user->id,
                'team_id' => $team->id,
                'member_id' => $member->id,
                'subscription_id' => $user->activeSubscription()->id ?? null
            ]);
        }
        
        return $result;
    }
}