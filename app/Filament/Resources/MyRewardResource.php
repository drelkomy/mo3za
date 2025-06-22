<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MyRewardResource\Pages;
use App\Models\Reward;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyRewardResource extends Resource
{
    protected static ?string $model = Reward::class;
    protected static ?string $navigationGroup = 'إدارة المهام';
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationLabel = 'مكافآتي';
    protected static ?int $navigationSort = 5;

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
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')->money('SAR'),
                Tables\Columns\TextColumn::make('reason')->label('السبب'),
                Tables\Columns\TextColumn::make('awardedBy.name')->label('منح بواسطة'),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ المنح')->dateTime(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->paginationPageOptions([5])
            ->defaultPaginationPageOption(5);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyRewards::route('/'),
        ];
    }
}