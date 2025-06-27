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
        $user = auth()->user();
        if (!$user) return false;
        if ($user->hasRole('admin')) return false;
        
        // استخدام التخزين المؤقت لتقليل الاستعلامات المتكررة
        $subscription = cache()->remember("user_{$user->id}_active_subscription", now()->addMinutes(10), function () use ($user) {
            return $user->activeSubscription;
        });
        
        return $subscription && $subscription->status === 'active';
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
            ->where('receiver_id', auth()->id())
            ->orderBy('created_at', 'desc');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('الملاحظات')
                    ->limit(50),
                Tables\Columns\TextColumn::make('giver.name')
                    ->label('منح بواسطة')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'completed',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'في الانتظار',
                        'completed' => 'مكتمل',
                        'rejected' => 'مرفوض',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ المنح')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->paginated()
            ->paginationPageOptions([5, 10, 25, 50])
            ->defaultPaginationPageOption(5);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyRewards::route('/'),
        ];
    }
}
