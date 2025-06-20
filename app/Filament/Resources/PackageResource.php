<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PackageResource\Pages;
use App\Models\Package;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;
    protected static ?string $navigationGroup = 'المحاسبة';
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'الباقات';
    protected static ?int $navigationSort = 3;

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
    
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('اسم الباقة')->required(),
            Forms\Components\Textarea::make('description')->label('الوصف'),
            Forms\Components\TextInput::make('price')->label('السعر')->numeric()->required(),
            Forms\Components\TextInput::make('max_tasks')->label('الحد الأقصى للمهام')->numeric()->required(),
            Forms\Components\TextInput::make('max_participants')->label('الحد الأقصى للمشاركين')->numeric()->required(),
            Forms\Components\TextInput::make('max_milestones_per_task')->label('الحد الأقصى للمراحل لكل مهمة')->numeric()->required(),
            Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('اسم الباقة')->searchable(),
                Tables\Columns\TextColumn::make('price')->label('السعر')->money('SAR'),
                Tables\Columns\TextColumn::make('max_tasks')->label('المهام'),
                Tables\Columns\TextColumn::make('max_participants')->label('المشاركين'),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPackages::route('/'),
            'create' => Pages\CreatePackage::route('/create'),
            'edit' => Pages\EditPackage::route('/{record}/edit'),
        ];
    }
}