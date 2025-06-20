<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentStatusWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $totalPayments = Payment::count();

        return [
            Stat::make('إجمالي الإيرادات', number_format($totalRevenue, 2) . ' ريال')
                ->description('من المدفوعات المكتملة')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
            
            Stat::make('إجمالي المدفوعات', $totalPayments)
                ->description('جميع المدفوعات')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin');
    }
}