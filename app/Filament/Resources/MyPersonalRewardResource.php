<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MyPersonalRewardResource\Pages;
use App\Models\Reward;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyPersonalRewardResource extends Resource
{
    protected static ?string $model = Reward::class;
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationLabel = 'مكافآتي';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->check() && !auth()->user()?->hasRole('admin');
    }
    
    public static function canCreate(): bool
    {
        return false;
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && !auth()->user()?->hasRole('admin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('receiver_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')->money('SAR'),
                Tables\Columns\TextColumn::make('notes')->label('الملاحظات'),
                Tables\Columns\TextColumn::make('giver.name')->label('منح بواسطة'),
                Tables\Columns\TextColumn::make('task.title')->label('المهمة'),
                Tables\Columns\TextColumn::make('status')->label('حالة الاستلام')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'في الانتظار',
                        'received' => 'تم الاستلام',
                        'completed' => 'مكتملة',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'received' => 'success',
                        'completed' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ المنح')->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm_receipt')
                    ->label('تأكيد الاستلام')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(fn (Reward $record) => $record->update(['status' => 'received']))
                    ->visible(fn (Reward $record) => $record->status === 'pending'),
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyPersonalRewards::route('/'),
        ];
    }
}