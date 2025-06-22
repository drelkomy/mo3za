<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Facades\Auth;

class SubscriptionStatsWidget extends StatsOverviewWidget
{
    protected function getCards(): array
    {
        $user = Auth::user()->loadCount('teams');
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return []; // لا تظهر أي بطاقات إن لم يكن هناك اشتراك نشط
        }

        $remainingTasks = max(0, $subscription->max_tasks - $subscription->tasks_created);

        // يفترض أن لدى المستخدم علاقة teams()
        $teamsCount = $user->teams_count;

        $taskStats = Task::selectRaw(
                "SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                 SUM(CASE WHEN status != 'completed' THEN 1 ELSE 0 END) as pending"
            )
            ->where('receiver_id', $user->id)
            ->first();

        $completedTasks = $taskStats->completed;
        $pendingTasks   = $taskStats->pending;

        $totalRewards   = Task::where('receiver_id', $user->id)->sum('reward_amount');

        return [
            Card::make('المهام المتبقية', $remainingTasks)
                ->description('من أصل ' . $subscription->max_tasks)
                ->color('success'),
            Card::make('عدد الفرق', $teamsCount)
                ->color('info'),
            Card::make('إجمالي المكافآت', number_format($totalRewards, 2) . ' ﷼')
                ->color('primary'),
            Card::make('المهام المنجزة', $completedTasks)
                ->color('success'),
            Card::make('المهام غير المنجزة', $pendingTasks)
                ->color('warning'),
        ];
    }

    public static function canView(): bool
    {
        // يظهر فقط إذا كان للمستخدم اشتراك نشط
        return Auth::check() && Auth::user()->hasActiveSubscription();
    }
}
