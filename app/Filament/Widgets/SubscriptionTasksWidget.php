<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use App\Models\Reward;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionTasksWidget extends BaseWidget
{
    public static bool $isLazy = true;
    
    protected int | string | array $columnSpan = 3;
    
    protected function getColumns(): int
    {
        return 3;
    }
    
    protected function getStats(): array
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasRole('داعم') || $user->hasRole('admin')) {
            return [];
        }
        
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return [];
        }

        // المهام المكتملة والمعلقة
        $completedTasks = Task::where('supporter_id', $user->id)
            ->where('status', 'completed')
            ->count();
        $pendingTasks = Task::where('supporter_id', $user->id)
            ->where('status', 'pending')
            ->count();
            
        // المكافآت
        $totalRewards = Reward::whereHas('task', function ($q) use ($user) {
            $q->where('supporter_id', $user->id);
        })->sum('amount');
        
        // المهام والمكافآت السابقة
        $previousCompletedTasks = $subscription->previous_tasks_completed ?? 0;
        $previousPendingTasks = $subscription->previous_tasks_pending ?? 0;
        $previousRewards = $subscription->previous_rewards_amount ?? 0;

        return [
            Stat::make('المهام المكتملة', $completedTasks)
                ->description('المهام المكتملة السابقة: ' . $previousCompletedTasks)
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
                
            Stat::make('المهام قيد التنفيذ', $pendingTasks)
                ->description('المهام المعلقة السابقة: ' . $previousPendingTasks)
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
                
            Stat::make('إجمالي المكافآت', number_format($totalRewards, 2) . ' ريال')
                ->description('المكافآت السابقة: ' . number_format($previousRewards, 2) . ' ريال')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
    
    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && $user->hasRole('داعم') && $user->hasActiveSubscription();
    }
}