<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use App\Models\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'الإشعارات';
    protected static ?int $navigationSort = 6;
    protected static bool $shouldRegisterNavigation = false;

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
        return $record->user_id === auth()->id();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('read_status')
                    ->label('')
                    ->icon(fn (Notification $record): string => $record->isRead() ? 'heroicon-o-envelope-open' : 'heroicon-s-envelope')
                    ->color(fn (Notification $record): string => $record->isRead() ? 'gray' : 'primary')
                    ->size('sm'),
                
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->weight(fn (Notification $record) => $record->isRead() ? 'normal' : 'bold')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('message')
                    ->label('الرسالة')
                    ->limit(50)
                    ->weight(fn (Notification $record) => $record->isRead() ? 'normal' : 'medium'),
                
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'info' => 'معلومات',
                        'success' => 'نجاح',
                        'warning' => 'تحذير',
                        'error' => 'خطأ',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'info' => 'info',
                        'success' => 'success',
                        'warning' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_read')
                    ->label('تحديد كمقروء')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(fn (Notification $record) => $record->markAsRead())
                    ->visible(fn (Notification $record) => !$record->isRead()),
                
                Tables\Actions\Action::make('open_link')
                    ->label('فتح الرابط')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Notification $record) => $record->action_url)
                    ->openUrlInNewTab()
                    ->visible(fn (Notification $record) => !empty($record->action_url)),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('mark_all_read')
                    ->label('تحديد الكل كمقروء')
                    ->icon('heroicon-o-check-circle')
                    ->action(function ($records) {
                        $records->each->markAsRead();
                    }),
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
        ];
    }
}