<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'إدارة الفرق';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin');
    }

    public static function getNavigationLabel(): string
    {
        return 'الفرق';
    }

    public static function getModelLabel(): string
    {
        return 'فريق';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الفرق';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات الفريق')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم الفريق')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('owner_id')
                            ->label('عضو الفريق')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('وصف الفريق')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                if ($user && $user->hasRole('member')) {
                    $query->where('owner_id', $user->id);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم الفريق')
                    ->searchable(),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('عضو الفريق')
                    ->searchable(),
                Tables\Columns\TextColumn::make('members_names')
                    ->label('أعضاء الفريق')
                    ->getStateUsing(fn($record) => $record->members->pluck('name')->implode('، ')),
                Tables\Columns\TextColumn::make('members_count')
                    ->label('عدد الأعضاء')
                    ->counts('members'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('نشط'),
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
        return [
            //
        ];
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }
}