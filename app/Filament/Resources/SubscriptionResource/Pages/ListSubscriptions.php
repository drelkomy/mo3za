<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptions extends ListRecords
{
    protected ?string $heading = 'سجل الاشتراكات';
    
    public function getSubheading(): ?string
    {
        $user = auth()->user();
        
        if ($user && $user->hasRole('داعم')) {
            $activeSubscription = $user->activeSubscription;
            
            if ($activeSubscription) {
                $endDate = $activeSubscription->end_date ? \Carbon\Carbon::parse($activeSubscription->end_date)->format('Y-m-d') : 'غير محدد';
                return "الاشتراك الحالي: {$activeSubscription->package->name} - ينتهي في: {$endDate}";
            } else {
                return "لا يوجد اشتراك نشط حالياً";
            }
        }
        
        return null;
    }
    protected static string $resource = SubscriptionResource::class;

    public function getTabs(): array
    {
        $user = auth()->user();
        
        if ($user && $user->hasRole('داعم')) {
            return [
                'all' => \Filament\Resources\Components\Tab::make('جميع الاشتراكات')
                    ->badge(\App\Models\Subscription::where('user_id', $user->id)->count()),
                'active' => \Filament\Resources\Components\Tab::make('الاشتراكات النشطة')
                    ->badge(\App\Models\Subscription::where('user_id', $user->id)
                        ->where('status', 'active')
                        ->where(function ($query) {
                            $query->whereNull('end_date')
                                ->orWhere('end_date', '>', now());
                        })
                        ->count())
                    ->modifyQueryUsing(fn ($query) => $query->where('status', 'active')
                        ->where(function ($q) {
                            $q->whereNull('end_date')
                                ->orWhere('end_date', '>', now());
                        })),
                'expired' => \Filament\Resources\Components\Tab::make('الاشتراكات المنتهية')
                    ->badge(\App\Models\Subscription::where('user_id', $user->id)
                        ->where(function ($query) {
                            $query->where('status', '!=', 'active')
                                ->orWhere('end_date', '<=', now());
                        })
                        ->count())
                    ->modifyQueryUsing(fn ($query) => $query->where(function ($q) {
                        $q->where('status', '!=', 'active')
                            ->orWhere('end_date', '<=', now());
                    })),
            ];
        }
        
        return [];
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // مدير النظام يمكنه الوصول دائماً
        if ($user->hasRole('مدير نظام')) {
            return true;
        }
        
        // الداعم يمكنه الوصول دائماً حتى لو انتهى اشتراكه
        if ($user->hasRole('داعم')) {
            return true;
        }
        
        return false;
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        
        if ($user && $user->hasRole('مدير نظام')) {
            return [
                Actions\CreateAction::make(),
            ];
        }
        
        return [];
    }
}
