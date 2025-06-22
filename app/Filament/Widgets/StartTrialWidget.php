<?php

namespace App\Filament\Widgets;

use App\Services\SubscriptionService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class StartTrialWidget extends Widget
{
    protected static string $view = 'filament.widgets.start-trial-widget';

    protected int | string | array $columnSpan = 'full';

    public function startTrial(SubscriptionService $subscriptionService): void
    {
        $user = Filament::auth()->user();

        if ($user->activeSubscription) {
            Notification::make()
                ->title(__('You already have an active subscription.'))
                ->warning()
                ->send();
            return;
        }

        $subscription = $subscriptionService->createTrialSubscription($user);

        if ($subscription) {
            Notification::make()
                ->title(__('Trial started successfully!'))
                ->success()
                ->send();

            // Refresh the page to show the new subscription state
            $this->js('window.location.reload()');
            return;
        }

        Notification::make()
            ->title(__('Failed to start trial.'))
            ->danger()
            ->send();
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        // Only show this widget if the user has no active subscription and has not used the trial yet
        // إظهار فقط للمستخدمين غير الأدمن الذين لا يملكون اشتراكًا نشطًا ولم يستخدموا التجربة
        return !$user->activeSubscription && !$user->trial_used && !$user->hasRole('admin');
    }
}
