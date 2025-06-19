<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationGroup = 'المحاسبة';
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'المدفوعات';
    protected static ?int $navigationSort = 4;
    protected static ?string $label = 'مدفوعات';
    protected static ?string $pluralLabel = 'المدفوعات';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('member');
    }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('member');
    }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('member');
    }
    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('member');
    }
    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('member');
    }
    public static function canReplicate(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('member');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->hasRole('member')) {
            // Members see only their payments
            return $query->where('user_id', $user->id);
        }

        // Admins see all payments
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->label('العضو')->relationship('user', 'name')->searchable()->required(),
            Forms\Components\Select::make('subscription_id')->label('الاشتراك')->relationship('subscription', 'id')->searchable(),
            Forms\Components\TextInput::make('amount')->label('المبلغ')->money('sar')->required(),
            Forms\Components\TextInput::make('payment_method')->label('طريقة الدفع'),
            Forms\Components\TextInput::make('transaction_id')->label('رقم العملية'),
            Forms\Components\Select::make('status')->label('الحالة')->options(self::getStatusOptions())->required(),
            Forms\Components\Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('العضو')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subscription.id')->label('الاشتراك')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')->money('sar')->sortable(),
                Tables\Columns\TextColumn::make('payment_method')->label('طريقة الدفع')->searchable(),
                Tables\Columns\TextColumn::make('transaction_id')->label('رقم العملية')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => self::getStatusColors()[$state] ?? 'gray')
                    ->formatStateUsing(fn (string $state): string => self::getStatusOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإنشاء')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(self::getStatusOptions()),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin');
    }

    protected static function getStatusOptions(): array
    {
        return [
            'completed' => 'مكتملة',
            'pending' => 'قيد الانتظار',
            'failed' => 'فاشلة',
        ];
    }

    protected static function getStatusColors(): array
    {
        return [
            'completed' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
        ];
    }
}