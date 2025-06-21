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

        // Get all tasks created by the user
        $tasks = Task::where('creator_id', $user->id)->get();
        $taskIds = $tasks->pluck('id');

        // Calculate total tasks
        $totalTasks = $tasks->count();

        // Calculate total stages from the tasks created by the user
        // This assumes a 'stages' relationship exists on the Task model
        $totalStages = $tasks->reduce(function ($carry, $task) {
            return $carry + $task->stages->count();
        }, 0);

        // Calculate total rewards given by the user
        $totalRewards = Reward::where('giver_id', $user->id)->sum('amount');

        return [
            Stat::make('إجمالي المهام التي أنشأتها', $totalTasks)
                ->description('العدد الكلي للمهام الموزعة على فريقك')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('success'),
                
            Stat::make('إجمالي المراحل', $totalStages)
                ->description('مجموع كل المراحل في جميع المهام')
                ->descriptionIcon('heroicon-m-list-bullet')
                ->color('info'),
                
            Stat::make('إجمالي المكافآت الموزعة', number_format($totalRewards, 2) . ' ريال')
                ->description('مجموع المبالغ التي تم توزيعها كمكافآت')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
        ];
    }
    
    public static function canView(): bool
    {
        $user = auth()->user();
        // Show this widget only to subscribed users who are not admins.
        return $user && $user->hasActiveSubscription() && !$user->hasRole('admin');
    }
}