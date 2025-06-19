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
        // عرض الإحصائيات لمدير النظام فقط
        if (!auth()->user()?->hasRole('مدير نظام')) {
            return [];
        }
        
        $stats = [];
        
        $packages = Package::where('is_active', true)->get();
        
        foreach ($packages as $package) {
            $activeSubscriptions = Subscription::where('package_id', $package->id)
                ->where('status', 'active')
                ->count();
                
            $stats[] = Stat::make($package->name, $activeSubscriptions)
                ->description('مشترك نشط')
                ->descriptionIcon('heroicon-m-users')
                ->color($package->is_trial ? 'info' : 'success');
        }
        
        return $stats;
    }
}