<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_expires_when_tasks_limit_reached(): void
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create a package
        $package = Package::create([
            'name' => 'Test Package',
            'price' => 99.99,
            'max_tasks' => 5,
            'max_participants' => 10,
            'max_milestones_per_task' => 3,
            'is_active' => true,
        ]);
        
        // Create a subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'price_paid' => $package->price,
            'tasks_created' => 4,
            'participants_created' => 2,
            'max_tasks' => $package->max_tasks,
            'max_participants' => $package->max_participants,
            'max_milestones_per_task' => $package->max_milestones_per_task,
            'start_date' => now(),
            'end_date' => null,
        ]);
        
        // Check if user can add tasks
        $this->assertTrue($user->canAddTasks());
        
        // Increment tasks created
        $subscription->tasks_created = 5;
        $subscription->save();
        
        // Refresh user model
        $user->refresh();
        
        // Check if user can no longer add tasks
        $this->assertFalse($user->canAddTasks());
        
        // Check if subscription is expired
        $this->assertTrue($subscription->isExpired());
    }

    public function test_subscription_expires_when_participants_limit_reached(): void
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create a package
        $package = Package::create([
            'name' => 'Test Package',
            'price' => 99.99,
            'max_tasks' => 10,
            'max_participants' => 5,
            'max_milestones_per_task' => 3,
            'is_active' => true,
        ]);
        
        // Create a subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'price_paid' => $package->price,
            'tasks_created' => 2,
            'participants_created' => 4,
            'max_tasks' => $package->max_tasks,
            'max_participants' => $package->max_participants,
            'max_milestones_per_task' => $package->max_milestones_per_task,
            'start_date' => now(),
            'end_date' => null,
        ]);
        
        // Check if user can add participants
        $this->assertTrue($user->canAddParticipants());
        
        // Increment participants created
        $subscription->participants_created = 5;
        $subscription->save();
        
        // Refresh user model
        $user->refresh();
        
        // Check if user can no longer add participants
        $this->assertFalse($user->canAddParticipants());
        
        // Check if subscription is expired
        $this->assertTrue($subscription->isExpired());
    }
}