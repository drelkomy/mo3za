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
        return [
            'الكل' => Tab::make()
                ->badge(JoinRequest::count()),
            'في الانتظار' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(JoinRequest::where('status', 'pending')->count()),
            'مقبول' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'accepted'))
                ->badge(JoinRequest::where('status', 'accepted')->count()),
            'مرفوض' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected'))
                ->badge(JoinRequest::where('status', 'rejected')->count()),
        ];
    }
}
