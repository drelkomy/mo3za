<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvitationResource\Pages;
use App\Models\Invitation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'إدارة الأعضاء';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'الدعوات';
    }

    public static function getModelLabel(): string
    {
        return 'دعوة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الدعوات';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات الدعوة')
                    ->schema([
                        Forms\Components\Select::make('sender_id')
                            ->label('المدير')
                            ->relationship('sender', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('team_id')
                            ->label('الفريق')
                            ->relationship('team', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'pending' => 'قيد الانتظار',
                                'accepted' => 'مقبولة',
                                'rejected' => 'مرفوضة',
                            ])
                            ->default('pending')
                            ->required(),
                        Forms\Components\Textarea::make('message')
                            ->label('الرسالة')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('تاريخ الانتهاء'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sender.name')
                    ->label('المدير')
                    ->searchable(),
                Tables\Columns\TextColumn::make('team.name')
                    ->label('الفريق')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable(),
                Tables\Columns\SelectColumn::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد الانتظار',
                        'accepted' => 'مقبولة',
                        'rejected' => 'مرفوضة',
                    ]),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('تاريخ الانتهاء')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد الانتظار',
                        'accepted' => 'مقبولة',
                        'rejected' => 'مرفوضة',
                    ]),
                Tables\Filters\SelectFilter::make('team_id')
                    ->label('الفريق')
                    ->relationship('team', 'name'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvitations::route('/'),
            'create' => Pages\CreateInvitation::route('/create'),
            'edit' => Pages\EditInvitation::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('member');
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('admin') || (auth()->user()?->hasRole('member') && auth()->user()?->hasActiveSubscription());
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin') || (auth()->user()?->hasRole('member') && $record->team_id === auth()->user()?->team_id);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin') || (auth()->user()?->hasRole('member') && $record->team_id === auth()->user()?->team_id);
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin') || (auth()->user()?->hasRole('member') && $record->team_id === auth()->user()?->team_id);
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin') || (auth()->user()?->hasRole('member') && $record->team_id === auth()->user()?->team_id);
    }

    public static function canReplicate(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin') || (auth()->user()?->hasRole('member') && $record->team_id === auth()->user()?->team_id);
    }
}