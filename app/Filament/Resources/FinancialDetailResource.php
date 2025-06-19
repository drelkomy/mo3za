<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinancialDetailResource\Pages;
use App\Models\FinancialDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinancialDetailResource extends Resource
{
    protected static ?string $model = FinancialDetail::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'البيانات العضولية';
    }

    public static function getModelLabel(): string
    {
        return 'بيانات عضولية';
    }

    public static function getPluralModelLabel(): string
    {
        return 'البيانات العضولية';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin');
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

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin');
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin');
    }

    public static function canReplicate(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('البيانات العضولية')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('العضو')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('رقم الواتساب')
                            ->tel(),
                        Forms\Components\TextInput::make('phone_number')
                            ->label('رقم الهاتف')
                            ->tel(),
                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email(),
                        Forms\Components\Textarea::make('bank_account_details')
                            ->label('تفاصيل الحساب البنكي')
                            ->rows(3),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('whatsapp_number')
                    ->label('رقم الواتساب')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone_number')
                    ->label('رقم الهاتف')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
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
            'index' => Pages\ListFinancialDetails::route('/'),
            'edit' => Pages\EditFinancialDetail::route('/{record}/edit'),
        ];
    }
}