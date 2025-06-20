<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskStagesResource\Pages;
use App\Models\Task;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaskStagesResource extends Resource
{
    protected static ?string $model = Task::class;
    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationLabel = 'مراحل المهام';
    protected static ?int $navigationSort = 3;
    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->check();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [
            'stages' => Pages\TaskStages::route('/{record}/stages'),
        ];
    }
}