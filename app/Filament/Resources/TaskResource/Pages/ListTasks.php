<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        
        return [
            Actions\CreateAction::make()
                ->visible(fn() => $user->hasRole('داعم') && !is_null($user->activeSubscription)),
        ];
    }
    
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('جميع المهام')
                ->badge(function () {
                    $user = auth()->user();
                    if ($user->hasRole('داعم')) {
                        return \App\Models\Task::where('supporter_id', $user->id)->count();
                    }
                    return \App\Models\Task::count();
                }),
            'pending' => Tab::make('قيد التنفيذ')
                ->badge(function () {
                    $user = auth()->user();
                    if ($user->hasRole('داعم')) {
                        return \App\Models\Task::where('supporter_id', $user->id)
                            ->where('status', 'pending')
                            ->count();
                    }
                    return \App\Models\Task::where('status', 'pending')->count();
                })
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),
            'completed' => Tab::make('منجزة')
                ->badge(function () {
                    $user = auth()->user();
                    if ($user->hasRole('داعم')) {
                        return \App\Models\Task::where('supporter_id', $user->id)
                            ->where('status', 'completed')
                            ->count();
                    }
                    return \App\Models\Task::where('status', 'completed')->count();
                })
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed')),
            'overdue' => Tab::make('متأخرة')
                ->badge(function () {
                    $user = auth()->user();
                    if ($user->hasRole('داعم')) {
                        return \App\Models\Task::where('supporter_id', $user->id)
                            ->where('status', 'overdue')
                            ->count();
                    }
                    return \App\Models\Task::where('status', 'overdue')->count();
                })
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'overdue')),
        ];
    }
}