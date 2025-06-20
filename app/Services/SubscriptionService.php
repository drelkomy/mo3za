<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionService
{
    public function createTrialSubscription(User $user): ?Subscription
    {
        $trialPackage = Package::where('name', 'الباقة التجريبية')->first();
        
        if (!$trialPackage) {
            return null;
        }

        return Subscription::create([
            'user_id' => $user->id,
            'package_id' => $trialPackage->id,
            'status' => 'active',
            'price_paid' => 0,
            'max_tasks' => $trialPackage->max_tasks,
            'max_participants' => $trialPackage->max_participants,
            'max_milestones_per_task' => $trialPackage->max_milestones_per_task,
            'tasks_created' => 0,
            'participants_created' => 0,
            'previous_tasks_completed' => 0,
            'previous_tasks_pending' => 0,
            'previous_rewards_amount' => 0,
        ]);
    }

    public function canAddTasks(User $user): bool
    {
        $subscription = $user->activeSubscription();
        return $subscription && 
               $subscription->status === 'active' && 
               $subscription->tasks_created < $subscription->max_tasks;
    }

    public function canAddParticipants(User $user): bool
    {
        $subscription = $user->activeSubscription();
        return $subscription && 
               $subscription->status === 'active' && 
               $subscription->participants_created < $subscription->max_participants;
    }

    public function incrementTasksCreated(User $user): bool
    {
        $subscription = $user->activeSubscription();
        if ($this->canAddTasks($user)) {
            $subscription->increment('tasks_created');
            return true;
        }
        return false;
    }

    public function incrementParticipantsCreated(User $user): bool
    {
        $subscription = $user->activeSubscription();
        if ($this->canAddParticipants($user)) {
            $subscription->increment('participants_created');
            return true;
        }
        return false;
    }
}