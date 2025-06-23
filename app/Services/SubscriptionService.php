<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function createTrialSubscription(User $user): ?Subscription
    {
        $trialPackage = Package::where('name', 'الباقة التجريبية')->first();
        
        if (!$trialPackage || $user->trial_used) {
            return null;
        }

        $user->update(['trial_used' => true]);

        return Subscription::create([
            'user_id' => $user->id,
            'package_id' => $trialPackage->id,
            'status' => 'active',
            'price_paid' => 0,
            'max_tasks' => $trialPackage->max_tasks,
            'max_milestones_per_task' => $trialPackage->max_milestones_per_task,
            'tasks_created' => 0,
            'previous_tasks_completed' => 0,
            'previous_tasks_pending' => 0,
            'previous_rewards_amount' => 0,
        ]);
    }

    /**
     * Renew a subscription after successful payment
     */
    public function renewSubscription(User $user, string $packageId, float $amountPaid): ?Subscription
    {
        $package = Package::find($packageId);
        
        if (!$package) {
            return null;
        }

        // Get current subscription to update previous stats
        $currentSubscription = $user->activeSubscription;
        
        return Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'price_paid' => $amountPaid,
            'max_tasks' => $package->max_tasks,
            'max_milestones_per_task' => $package->max_milestones_per_task,
            'tasks_created' => 0,
            'previous_tasks_completed' => $currentSubscription?->previous_tasks_completed ?? 0,
            'previous_tasks_pending' => $currentSubscription?->previous_tasks_pending ?? 0,
            'previous_rewards_amount' => $currentSubscription?->previous_rewards_amount ?? 0,
        ]);
    }

 

    public function canAddTeamMembers(User $user): bool
    {
        $subscription = $user->activeSubscription;
        return $subscription && !($subscription->isExpired());
    }

    public function incrementTasksCreated(User $user): bool
    {
        $subscription = $user->activeSubscription;
        if ($user->canAddTasks()) {
            $subscription->increment('tasks_created');
        
        // Refresh the model to get the updated tasks_created value
        $subscription->refresh();
        
        // تحقق من انتهاء الباقة عند الوصول للحد الأقصى
        if ($subscription->tasks_created >= $subscription->max_tasks) {
                $subscription->update(['status' => 'expired']);
                
                // Log the expiration and note the need for user redirection
                Log::info('Subscription expired due to tasks limit. User should be redirected to renewal page.', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'tasks_created' => $subscription->tasks_created,
                    'max_tasks' => $subscription->max_tasks
                ]);
            }
            
            return true;
        }
        return false;
    }
}
