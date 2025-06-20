<?php

namespace App\Filament\Widgets;

use App\Models\Package;
use Filament\Widgets\Widget;

class PackageSubscriptionWidget extends Widget
{
    protected static string $view = 'filament.widgets.package-subscription-widget';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 1;

    public function getPackages()
    {
        return Package::where('is_active', true)->get();
    }

    public static function canView(): bool
    {
        return auth()->check() && !auth()->user()?->hasActiveSubscription() && !auth()->user()?->hasRole('admin');
    }
}