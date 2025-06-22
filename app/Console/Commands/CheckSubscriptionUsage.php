<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckSubscriptionUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-usage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check subscription usage and update status if limits are reached';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking subscription usage...');
        
        // Get all active subscriptions
        $subscriptions = Subscription::where('status', 'active')->get();
        
        $this->info("Found {$subscriptions->count()} active subscriptions");
        
        $updated = 0;
        
        foreach ($subscriptions as $subscription) {


            // Check if tasks limit is reached
            if ($subscription->tasks_created >= $subscription->max_tasks) {
                $subscription->status = 'expired';
                $subscription->save();
                
                Log::info('Subscription expired due to tasks limit', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'tasks_created' => $subscription->tasks_created,
                    'max_tasks' => $subscription->max_tasks
                ]);
                
                $updated++;
                continue;
            }
            
        }
        
        $this->info("Updated {$updated} subscriptions to expired status");
        
        return Command::SUCCESS;
    }
}