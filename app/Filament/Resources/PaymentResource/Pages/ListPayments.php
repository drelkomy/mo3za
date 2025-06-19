<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

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
        return [];
    }
}
