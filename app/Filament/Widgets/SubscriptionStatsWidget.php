<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use App\Models\TaskReward as Reward;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionStatsWidget extends BaseWidget
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
        
        if (!$user || !$user->hasRole('داعم')) {
            return [
                Stat::make('حالة الاشتراك', 'غير مشترك')
                    ->description('لا يوجد اشتراك نشط')
                    ->descriptionIcon('heroicon-m-x-circle')
                    ->color('danger'),
            ];
        }
        
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return [
                Stat::make('حالة الاشتراك', 'غير مشترك')
                    ->description('لا يوجد اشتراك نشط')
                    ->descriptionIcon('heroicon-m-x-circle')
                    ->color('danger'),
            ];
        }

        // المهام المتبقية
        $usedTasks = $user->createdTasks()->where('created_at', '>=', $subscription->start_date)->count();
        $remainingTasks = max(0, $subscription->max_tasks - $usedTasks);
        $totalTasks = $subscription->max_tasks;
        $tasksPercentage = $totalTasks > 0 ? round(($usedTasks / $totalTasks) * 100) : 0;

        // المشاركين المتبقين
        $usedParticipants = $user->participants()->count();
        $remainingParticipants = max(0, $subscription->max_participants - $usedParticipants);
        $totalParticipants = $subscription->max_participants;
        $participantsPercentage = $totalParticipants > 0 ? round(($usedParticipants / $totalParticipants) * 100) : 0;
        
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
        
        $isConsumptionBased = ($subscription->max_tasks > 0) || ($subscription->max_participants > 0);

        if ($isConsumptionBased) {
            $packageDescription = 'تنتهي عند استهلاك حدود الباقة';
            $packageIcon = 'heroicon-m-clipboard-document-check';
        } else {
            $packageDescription = $subscription->end_date ? 'تنتهي في ' . $subscription->end_date->format('Y-m-d') : 'باقة دائمة';
            $packageIcon = 'heroicon-m-calendar';
        }

        return [
            Stat::make('الباقة', $subscription->package->name)
                ->description($packageDescription)
                ->descriptionIcon($packageIcon)
                ->color('primary'),
                
            Stat::make('المهام المتبقية', $remainingTasks . ' / ' . $totalTasks)
                ->description('استهلاك ' . $tasksPercentage . '% من المهام')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($remainingTasks > 0 ? 'success' : 'danger'),
                
            Stat::make('المشاركين المتبقين', $remainingParticipants . ' / ' . $totalParticipants)
                ->description('استهلاك ' . $participantsPercentage . '% من المشاركين')
                ->descriptionIcon('heroicon-m-user-group')
                ->color($remainingParticipants > 0 ? 'success' : 'danger'),
        ];
    }
}