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
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'المدفوعات';
    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->check();
    }
    
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        if (!auth()->user()?->hasRole('admin')) {
            $query->where('user_id', auth()->id());
        }
        
        return $query;
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('المستخدم')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('package_id')
                    ->label('الباقة')
                    ->relationship('package', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('order_id')
                    ->label('رقم الطلب')
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->label('المبلغ')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('currency')
                    ->label('العملة')
                    ->default('SAR')
                    ->required(),
                Forms\Components\TextInput::make('customer_name')
                    ->label('اسم العميل')
                    ->required(),
                Forms\Components\TextInput::make('customer_email')
                    ->label('بريد العميل')
                    ->email()
                    ->required(),
                Forms\Components\TextInput::make('customer_phone')
                    ->label('هاتف العميل'),
                Forms\Components\Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد الانتظار',
                        'completed' => 'مكتمل',
                        'failed' => 'فاشل',
                        'cancelled' => 'ملغي',
                    ])
                    ->default('pending')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('الوصف'),
                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_id')
                    ->label('رقم الطلب')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('package.name')
                    ->label('الباقة')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->user()->hasRole('admin')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()->hasRole('admin')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasRole('admin')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}