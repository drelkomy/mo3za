<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionStatusWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 4;

    protected function getStats(): array
    {
        $subscription = auth()->user()->activeSubscription() ?? auth()->user()->subscriptions()->latest()->first();
        
        if (!$subscription) {
            return [
                Stat::make('لا يوجد اشتراك', 'يمكنك العمل بحرية')
                    ->color('info'),
            ];
        }
        
        // حساب المتبقي من الباقة
        $remainingTasks = max(0, $subscription->max_tasks - $subscription->tasks_created);
        $remainingParticipants = max(0, $subscription->max_participants - $subscription->participants_created);
        $statusColor = $subscription->status === 'active' ? 'success' : 'warning';
        $statusText = $subscription->status === 'active' ? 'نشط' : 'منتهي';
        
        return [
            Stat::make('الباقة', $subscription->package->name)
                ->description("الحالة: {$statusText}")
                ->color($statusColor),
            
            Stat::make('المهام المتبقية', $remainingTasks)
                ->description("من أصل {$subscription->max_tasks}")
                ->color($remainingTasks > 0 ? 'success' : 'danger'),
            
            Stat::make('المشاركين المتبقين', $remainingParticipants)
                ->description("من أصل {$subscription->max_participants}")
                ->color($remainingParticipants > 0 ? 'success' : 'danger'),
            
            Stat::make('المراحل لكل مهمة', $subscription->max_milestones_per_task)
                ->description('الحد الأقصى')
                ->color('info'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->check() && !auth()->user()?->hasRole('admin');
    }
}