<?php

namespace App\Observers;

use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class SubscriptionObserver
{
    /**
     * Handle the Subscription "updated" event.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    public function updated(Subscription $subscription)
    {
        // Check if any usage-related fields were updated
        $changed = $subscription->getDirty();
        
        // Only check expiration if usage-related fields were updated
        if (isset($changed['tasks_created'])) {
            $this->checkExpiration($subscription);
        }
    }

    /**
     * Check if subscription should be expired based on usage limits.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function checkExpiration(Subscription $subscription)
    {
        if ($subscription->status !== 'active') {
            return;
        }

        // Check tasks limit
        if ($subscription->tasks_created >= $subscription->max_tasks) {
            $this->expireSubscription($subscription, 'tasks');
            return;
        }

    }

    /**
     * Expire the subscription and log the reason.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  string  $reason
     * @return void
     */
    protected function expireSubscription(Subscription $subscription, string $reason)
    {
        $subscription->status = 'expired';
        $subscription->save();

        Log::info('Subscription expired', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'reason' => $reason,
            'tasks_created' => $subscription->tasks_created,
            'max_tasks' => $subscription->max_tasks,
        ]);
    }
}
