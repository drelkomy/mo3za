<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RewardResource\Pages;
use App\Models\Reward;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RewardResource extends Resource
{
    protected static ?string $model = Reward::class;
    protected static ?string $navigationGroup = 'إدارة الفرق';
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationLabel = 'مكافآت الفريق';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->hasRole('admin')) return true;
        
        $subscription = $user->activeSubscription;
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
        $user = auth()->user();
        if (!$user) return false;
        if ($user->hasRole('admin')) return true;
        
        $subscription = $user->activeSubscription;
        return $subscription && $subscription->status === 'active';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        if (!auth()->user()?->hasRole('admin')) {
            // عرض المكافآت التي منحها الشخص فقط
            $query->where('giver_id', auth()->id());
        }
        
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('receiver_id')->label('المستخدم')->relationship('receiver', 'name')->required(),
            Forms\Components\TextInput::make('amount')->label('المبلغ')->numeric()->required(),
            Forms\Components\Textarea::make('notes')->label('الملاحظات'),
            Forms\Components\Hidden::make('giver_id')->default(auth()->id()),
            Forms\Components\Hidden::make('status')->default('pending'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receiver.name')->label('المستخدم'),
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')->money('SAR'),
                Tables\Columns\TextColumn::make('notes')->label('الملاحظات'),
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
                Tables\Actions\Action::make('receive')
                    ->label('تم الاستلام')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn ($record) => $record->update(['status' => 'received']))
                    ->visible(fn ($record) => $record->receiver_id === auth()->id() && $record->status === 'completed')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد استلام المكافأة')
                    ->modalDescription('هل تم استلام هذه المكافأة؟'),
            ])
            ->paginationPageOptions([5])
            ->defaultPaginationPageOption(5);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewards::route('/'),
        ];
    }
}