<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource;
use App\Filament\Resources\TeamResource;
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
        $user = User::withoutEvents(fn () => User::factory()->create());
        $this->actingAs($user);

        // Create a package
        $package = Package::create([
            'name' => 'Test Package',
            'price' => 99.99,
            'max_tasks' => 5,
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
            'max_tasks' => $package->max_tasks,
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
        $user = $user->fresh();

        // Check if user can no longer add tasks
        $this->assertFalse($user->canAddTasks());

        // Check if subscription is expired
        $this->assertTrue($subscription->isExpired());
    }

    public function test_team_resource_visibility_when_task_limit_exceeded(): void
    {
        // Create a user
        $user = User::withoutEvents(fn () => User::factory()->create());
        $this->actingAs($user);

        // Create a package with low task limit
        $package = Package::create([
            'name' => 'Test Package',
            'price' => 99.99,
            'max_tasks' => 1,
            'max_milestones_per_task' => 3,
            'is_active' => true,
        ]);

        // Create a subscription
        Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'price_paid' => $package->price,
            'tasks_created' => 1,
            'max_tasks' => $package->max_tasks,
            'max_milestones_per_task' => $package->max_milestones_per_task,
            'start_date' => now(),
            'end_date' => null,
        ]);

        $user->refresh();
        $this->actingAs($user);

        // TeamResource should not be visible when task limit is exceeded
        $this->assertFalse(TeamResource::shouldRegisterNavigation());
        $this->assertFalse(TeamResource::canCreate());
        $this->assertFalse(TeamResource::canViewAny());
    }

    public function test_team_resource_visibility_when_subscription_expired(): void
    {
        // Create a user
        $user = User::withoutEvents(fn () => User::factory()->create());
        $this->actingAs($user);

        // Create a package
        $package = Package::create([
            'name' => 'Test Package',
            'price' => 99.99,
            'max_tasks' => 10,
            'max_milestones_per_task' => 3,
            'is_active' => true,
        ]);

        // Create expired subscription
        Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'expired',
            'price_paid' => $package->price,
            'tasks_created' => 5,
            'max_tasks' => $package->max_tasks,
            'max_milestones_per_task' => $package->max_milestones_per_task,
            'start_date' => now(),
            'end_date' => null,
        ]);

        $user->refresh();
        $this->actingAs($user);

        // TeamResource should not be visible when subscription is expired
        $this->assertFalse(TeamResource::shouldRegisterNavigation());
        $this->assertFalse(TeamResource::canCreate());
        $this->assertFalse(TeamResource::canViewAny());
    }

    public function test_cannot_create_task_when_subscription_expired(): void
    {
        // Create a user
        $user = User::withoutEvents(fn () => User::factory()->create());
        $this->actingAs($user);

        // Create a package
        $package = Package::create([
            'name' => 'Test Package',
            'price' => 99.99,
            'max_tasks' => 10,
            'max_milestones_per_task' => 3,
            'is_active' => true,
        ]);

        // Create expired subscription
        Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'expired',
            'price_paid' => $package->price,
            'tasks_created' => 5,
            'max_tasks' => $package->max_tasks,
            'max_milestones_per_task' => $package->max_milestones_per_task,
            'start_date' => now(),
            'end_date' => null,
        ]);

        $user->refresh();

        // Test TaskResource visibility
        $this->assertFalse(TaskResource::canCreate());

        // Test task creation authorization
        $this->assertFalse($user->canAddTasks());
    }

    public function test_cannot_create_task_when_task_limit_reached(): void
    {
        // Create a user
        $user = User::withoutEvents(fn () => User::factory()->create());
        $this->actingAs($user);

        // Create a package with low task limit
        $package = Package::create([
            'name' => 'Test Package',
            'price' => 99.99,
            'max_tasks' => 1,
            'max_milestones_per_task' => 3,
            'is_active' => true,
        ]);

        // Create subscription with task limit reached
        Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'price_paid' => $package->price,
            'tasks_created' => 1,
            'max_tasks' => $package->max_tasks,
            'max_milestones_per_task' => $package->max_milestones_per_task,
            'start_date' => now(),
            'end_date' => null,
        ]);

        $user->refresh();

        // Test TaskResource visibility
        $this->assertFalse(TaskResource::canCreate());

        // Test task creation authorization
        $this->assertFalse($user->canAddTasks());
    }
}