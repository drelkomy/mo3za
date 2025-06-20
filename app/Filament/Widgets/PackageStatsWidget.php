<?php

namespace App\Filament\Widgets;

use App\Models\Package;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PackageStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // عرض الإحصائيات للإدمن فقط
        if (!auth()->user()?->hasRole('admin')) {
            return [];
        }
        
        $stats = [];
        
        // عدد الاشتراكات على كل باقة
        $packages = Package::where('is_active', true)->get();
        foreach ($packages as $package) {
            $subscriptionsCount = \App\Models\Subscription::where('package_id', $package->id)->count();
            $stats[] = Stat::make($package->name, $subscriptionsCount)
                ->description('عدد الاشتراكات')
                ->descriptionIcon('heroicon-m-users')
                ->color($package->is_trial ? 'info' : 'success');
        }

        // عدد المهام الكلي
        $tasksCount = \App\Models\Task::count();
        $stats[] = Stat::make('عدد المهام', $tasksCount)
            ->description('إجمالي المهام')
            ->descriptionIcon('heroicon-m-clipboard-document-list')
            ->color('primary');

        // عدد الفرق الكلي
        $teamsCount = \App\Models\Team::count();
        $stats[] = Stat::make('عدد الفرق', $teamsCount)
            ->description('إجمالي الفرق')
            ->descriptionIcon('heroicon-m-users')
            ->color('warning');

        // عدد المكافآت الكلي
        $rewardsCount = \App\Models\Reward::count();
        $stats[] = Stat::make('عدد المكافآت', $rewardsCount)
            ->description('إجمالي المكافآت')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('success');

        return $stats;
    }
}