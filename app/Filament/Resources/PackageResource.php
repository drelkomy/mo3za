<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PackageResource\Pages;
use App\Models\Package;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'المحاسبة';
    protected static ?string $navigationLabel = 'الباقات';
    protected static ?string $pluralLabel = 'الباقات';
    protected static ?string $label = 'باقة';

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
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
        return $form->schema([
            Forms\Components\Section::make('معلومات الباقة الأساسية')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('اسم الباقة')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('price')
                        ->label('السعر')
                        ->required()
                        ->numeric()
                        ->prefix('SAR'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('فعالة')
                        ->required(),
                ])->columns(3),
            Forms\Components\Section::make('حدود استخدام الباقة')
                ->schema([
                    Forms\Components\TextInput::make('max_tasks')
                        ->label('أقصى عدد للمهام')
                        ->required()
                        ->numeric()
                        ->default(10),
                    Forms\Components\TextInput::make('max_members')
                        ->label('أقصى عدد للعضوين في المهمة')
                        ->required()
                        ->numeric()
                        ->default(5),
                    Forms\Components\TextInput::make('max_milestones_per_task')
                        ->label('أقصى عدد للمراحل لكل مهمة')
                        ->required()
                        ->numeric()
                        ->default(3),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم الباقة')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('السعر')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_tasks')
                    ->label('حد المهام')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_members')
                    ->label('حد العضوين')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعالة')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPackages::route('/'),
            'create' => Pages\CreatePackage::route('/create'),
            'edit' => Pages\EditPackage::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('مدير نظام') ?? false;
    }
}
