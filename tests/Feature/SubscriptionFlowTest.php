<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource;
use App\Filament\Widgets\StartTrialWidget;
use App\Filament\Widgets\UpgradeSubscriptionWidget;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionFlowTest extends TestCase
{
    use DatabaseMigrations;

    public function test_user_without_subscription_sees_start_trial_widget()
    {
        $user = User::withoutEvents(fn () => User::factory()->create());
        $this->actingAs($user);
        $user->refresh();

        Livewire::test(StartTrialWidget::class)
            ->assertSee('ابدأ التجربة');
    }

    public function test_user_can_start_trial_subscription()
    {
        $user = User::withoutEvents(fn () => User::factory()->create());
        $this->actingAs($user);
        $user->refresh();

        // Ensure the trial package exists before the test
        Package::firstOrCreate(['name' => 'الباقة التجريبية'], ['price' => 0, 'max_tasks' => 3, 'max_milestones_per_task' => 3]);

        $this->assertDatabaseMissing('subscriptions', ['user_id' => $user->id]);

        Livewire::test(StartTrialWidget::class)
            ->call('startTrial');

        $trialPackage = Package::where('name', 'الباقة التجريبية')->first();
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'package_id' => $trialPackage->id,
            'max_tasks' => 3,
            'status' => 'active',
        ]);

        $user->refresh();
        $this->assertTrue($user->canAddTasks());
    }

    public function test_ui_is_correct_when_trial_is_expired()
    {
        $user = User::withoutEvents(fn () => User::factory()->create());
        $this->actingAs($user);

        // Create an expired trial subscription
        $package = Package::factory()->create(['name' => 'الباقة التجريبية', 'max_tasks' => 3]);
        Subscription::factory()->for($user)->for($package)->create([
            'tasks_created' => 3,
            'max_tasks' => $package->max_tasks
        ]);
        Team::factory()->create(['owner_id' => $user->id]);

        $user->refresh();
        $this->actingAs($user);

        // Assert user cannot add tasks or members
        $this->assertFalse($user->canAddTeamMembers());
        $this->assertFalse($user->canAddTasks());

        // Assert upgrade widget is visible
        Livewire::test(UpgradeSubscriptionWidget::class)
            ->assertSee('Upgrade Your Subscription');

        // Assert start trial widget is not visible
        $this->assertFalse(StartTrialWidget::canView());
    }

    public function test_user_can_upgrade_subscription_after_trial_expires()
    {
        $user = User::withoutEvents(fn () => User::factory()->create());
        $this->actingAs($user);

        // Expired trial
        $trialPackage = Package::factory()->create(['name' => 'الباقة التجريبية', 'max_tasks' => 3]);
        Subscription::factory()->for($user)->for($trialPackage)->create(['tasks_created' => 3]);
        Team::factory()->create(['owner_id' => $user->id]);

        // Paid package
        $paidPackage = Package::factory()->create(['name' => 'الباقة المدفوعة', 'price' => 100, 'max_tasks' => 50]);

        // Simulate payment callback
        $subscriptionService = app(SubscriptionService::class);
        $subscriptionService->renewSubscription($user, $paidPackage->id, $paidPackage->price);

        $user->refresh();
        $this->actingAs($user);

        // Assert new subscription is active
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'package_id' => $paidPackage->id,
            'status' => 'active',
            'tasks_created' => 0,
        ]);

        // Assert user can now add tasks and members
        $this->assertTrue($user->canAddTasks());
        $this->assertTrue($user->canAddTeamMembers());

        // Assert upgrade widget is no longer visible
        $this->assertFalse(UpgradeSubscriptionWidget::canView());
    }
}

