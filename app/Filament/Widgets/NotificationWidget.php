<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class NotificationWidget extends Widget
{
    protected static string $view = 'filament.widgets.notification-widget';
    protected static ?int $sort = -1;
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->check() && !auth()->user()?->hasRole('admin');
    }
}