<?php

namespace App\Filament\Widgets;

use App\Models\Package;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class UpgradeSubscriptionWidget extends Widget
{
    protected static string $view = 'filament.widgets.upgrade-subscription-widget';

    protected int | string | array $columnSpan = 'full';

    public Collection $packages;

    public function mount(): void
    {
        $this->packages = Package::where('is_active', true)
            ->where('name', '!=', 'الباقة التجريبية')
            ->get();
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        // Show if the user لا يملك اشتراكًا نشطًا.
        // إظهار فقط للمستخدمين غير الأدمن الذين لا يملكون اشتراكًا نشطًا
        return !$user->hasActiveSubscription() && !$user->hasRole('admin');
    }
}
