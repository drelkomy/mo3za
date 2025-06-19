<?php

namespace App\Filament\Widgets;

use App\Models\Package;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class PackagesWidget extends Widget
{
    protected static string $view = 'filament.widgets.packages-widget';

    public function getPackagesProperty()
    {
        return Package::where('is_active', true)->get();
    }

    public static function canView(): bool
    {
        $user = Auth::user();
        return $user && $user->hasRole('داعم') && !$user->hasActiveSubscription();
    }
}
