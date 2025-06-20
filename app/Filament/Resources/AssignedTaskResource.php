<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssignedTaskResource\Pages;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssignedTaskResource extends Resource
{
    protected static ?string $model = Task::class;
    protected static ?string $navigationGroup = 'إدارة المهام';
    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static ?string $navigationLabel = 'المهام المسندة إلي';
    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->check() && !auth()->user()?->hasRole('admin');
    }
    
    public static function canCreate(): bool
    {
        return false;
    }
    
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
    
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && !auth()->user()?->hasRole('admin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('team.members', function($q) {
                $q->where('user_id', auth()->id());
            })
            ->where('created_by', '!=', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('العنوان')->searchable(),
                Tables\Columns\TextColumn::make('team.name')->label('الفريق'),
                Tables\Columns\TextColumn::make('creator.name')->label('منشئ المهمة'),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge(),
                Tables\Columns\TextColumn::make('due_date')->label('تاريخ الاستحقاق')->date(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssignedTasks::route('/'),
        ];
    }
}