<?php

namespace App\Filament\Resources\JoinRequestResource\Pages;

use App\Filament\Resources\JoinRequestResource;
use App\Models\JoinRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListJoinRequests extends ListRecords
{
    protected static string $resource = JoinRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
    
    public function getTabs(): array
    {
        $user = auth()->user();
        $baseQuery = JoinRequestResource::getEloquentQuery();
        
        return [
            'الكل' => Tab::make()
                ->badge($baseQuery->clone()->count()),
            'في الانتظار' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge($baseQuery->clone()->where('status', 'pending')->count()),
            'مقبول' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'accepted'))
                ->badge($baseQuery->clone()->where('status', 'accepted')->count()),
            'مرفوض' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected'))
                ->badge($baseQuery->clone()->where('status', 'rejected')->count()),
        ];
    }
    
    protected function getDefaultTab(): ?string
    {
        return 'في الانتظار';
    }
}
