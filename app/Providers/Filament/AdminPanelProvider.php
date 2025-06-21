<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->registration()
            ->font('cairo')
            ->topNavigation()
            ->globalSearch(false)
            ->userMenuItems([
                'notifications' => \Filament\Navigation\MenuItem::make()
                    ->label('الإشعارات')
                    ->url(fn (): string => \App\Filament\Resources\NotificationResource::getUrl())
                    ->icon('heroicon-o-bell')
                    ->visible(fn (): bool => auth()->check() && !auth()->user()?->hasRole('admin')),
            ])
            ->colors([
                'primary' =>'#006E82',
            ])
            ->resources([
                \App\Filament\Resources\UserResource::class,
                \App\Filament\Resources\TeamResource::class,
                \App\Filament\Resources\JoinRequestResource::class,
                \App\Filament\Resources\TaskResource::class,
                \App\Filament\Resources\RewardResource::class,
                \App\Filament\Resources\MyTaskResource::class,
                \App\Filament\Resources\MyPersonalRewardResource::class,
                \App\Filament\Resources\NotificationResource::class,
                \App\Filament\Resources\PackageResource::class,
                \App\Filament\Resources\SubscriptionResource::class,
                \App\Filament\Resources\PaymentResource::class,
                \App\Filament\Resources\FinancialDetailResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\Widgets\PackageSubscriptionWidget::class,
                \App\Filament\Widgets\SubscriptionStatusWidget::class,
                \App\Filament\Widgets\TechnicalSupportWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('web')
            ->loginRouteSlug('login')
            ->brandName('نظام إدارة المعزز')
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                'إدارة المستخدمين',
                'إدارة الفرق',
                'مهامي الشخصية',
                'المحاسبة',
                'الإعدادات',
            ])
            ->plugin(
                BreezyCore::make()
                    ->myProfile()
                    ->avatarUploadComponent(fn($fileUpload) => $fileUpload->disk('public')->directory('avatars'))
            );
    }
}