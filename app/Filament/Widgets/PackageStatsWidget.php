<?php

namespace App\Filament\Widgets;

use App\Models\Package;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use App\Models\Task;
use App\Models\Team;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Facades\Auth;

class PackageStatsWidget extends StatsOverviewWidget
{
    /**
     * عرض للعناصر على صف واحد بالكامل
     */
    protected int|string|array $columnSpan = 'full';

    protected function getCards(): array
    {
        // لا يُعرض شيء للمستخدمين غير الأدمن – التحقق احتياطاً
        if (! Auth::check() || ! Auth::user()->hasRole('admin')) {
            return [];
        }

        $cards = [];

        // جلب عدد الاشتراكات لكل باقة في استعلام واحد باستخدام withCount
        Package::query()->withCount('subscriptions')->orderBy('id')->get()->each(function (Package $package) use (&$cards) {
            $cards[] = Card::make("اشتراكات {$package->name}", $package->subscriptions_count)
                ->icon('heroicon-o-rectangle-stack')
                ->color('primary');
        });

        // إجماليات عامة
        $cards[] = Card::make('عدد المستخدمين', User::count())
            ->icon('heroicon-o-users')
            ->color('danger');
        $cards[] = Card::make('عدد الفرق', Team::count())
            ->icon('heroicon-o-users')
            ->color('info');

        $cards[] = Card::make('عدد المهام', Task::count())
            ->icon('heroicon-o-clipboard-document-list')
            ->color('success');

        $cards[] = Card::make('عدد المكافآت', Reward::count())
            ->icon('heroicon-o-currency-dollar')
            ->color('warning');

        return $cards;
    }

    public static function canView(): bool
    {
        return Auth::check() && Auth::user()->hasRole('admin');
    }
}
