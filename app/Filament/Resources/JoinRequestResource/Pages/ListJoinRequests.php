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
        $team = \App\Models\Team::where('owner_id', $user->id)
            ->orWhereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();
        
        $teamId = $team ? $team->id : null;
        
        return [
            'الكل' => Tab::make()
                ->badge($teamId ? JoinRequest::where('team_id', $teamId)->count() : 0),
            'في الانتظار' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->when($teamId, fn($q) => $q->where('team_id', $teamId)))
                ->badge($teamId ? JoinRequest::where('status', 'pending')->where('team_id', $teamId)->count() : 0),
            'مقبول' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'accepted')->when($teamId, fn($q) => $q->where('team_id', $teamId)))
                ->badge($teamId ? JoinRequest::where('status', 'accepted')->where('team_id', $teamId)->count() : 0),
            'مرفوض' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected')->when($teamId, fn($q) => $q->where('team_id', $teamId)))
                ->badge($teamId ? JoinRequest::where('status', 'rejected')->where('team_id', $teamId)->count() : 0),
        ];
    }
}
