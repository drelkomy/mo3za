<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RewardResource\Pages;
use App\Models\TaskReward;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RewardResource extends Resource
{
    protected static ?string $model = \App\Models\Reward::class;
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static bool $shouldRegisterNavigation = false;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    protected static ?string $navigationLabel = 'المكافآت';
    protected static ?string $pluralLabel = 'المكافآت';
    protected static ?string $label = 'مكافأة';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationGroup = null;



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
        $user = auth()->user();
        $isMember = $user->hasRole('member');
        
        $schema = [
            Forms\Components\Select::make('member_id')
                ->relationship('user', 'name', function ($query) use ($user, $isMember) {
                    if ($isMember) {
                        return $query->where('member_id', $user->id);
                    }
                    return $query;
                })
                ->label('عضو')
                ->required()
                ->searchable(),
            Forms\Components\Select::make('task_id')
                ->relationship('task', 'title', function ($query) use ($user, $isMember) {
                    if ($isMember) {
                        return $query->where('member_id', $user->id);
                    }
                    return $query;
                })
                ->label('المهمة')
                ->searchable(),
            Forms\Components\Select::make('type')
                ->label('نوع المكافأة')
                ->options([
                    'cash' => 'نقدية',
                    'other' => 'أخرى',
                ])
                ->default('cash')
                ->required()
                ->reactive(),
            Forms\Components\TextInput::make('amount')
                ->label('المبلغ')
                ->numeric()
                ->prefix('SAR')
                ->required()
                ->visible(fn (callable $get) => $get('type') === 'cash'),
            Forms\Components\TextInput::make('description')
                ->label('الوصف')
                ->maxLength(255)
                ->required(fn (callable $get) => $get('type') === 'other')
                ->visible(fn (callable $get) => $get('type') === 'other'),
            Forms\Components\Select::make('status')
                ->label('الحالة')
                ->options([
                    'pending' => 'قيد الانتظار',
                    'completed' => 'مكتملة',
                    'paid' => 'تم الدفع',
                    'cancelled' => 'ملغية',
                ])
                ->default('paid')
                ->required(),
            Forms\Components\DateTimePicker::make('awarded_at')
                ->label('تاريخ المنح')
                ->default(now()),
        ];
        
        return $form->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('task.title')
                    ->label('المهمة')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع المكافأة')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'cash' => 'نقدية',
                        'other' => 'أخرى',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'other' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->limit(30),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'paid' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'pending' => 'قيد الانتظار',
                        'completed' => 'مكتملة',
                        'paid' => 'تم الدفع',
                        'cancelled' => 'ملغية',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('awarded_at')
                    ->label('تاريخ المنح')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد الانتظار',
                        'completed' => 'مكتملة',
                        'paid' => 'تم الدفع',
                        'cancelled' => 'ملغية',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع المكافأة')
                    ->options([
                        'cash' => 'نقدية',
                        'other' => 'أخرى',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->status !== 'completed'),
            ])
            ->bulkActions([]);
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
            'index' => Pages\ListRewards::route('/'),
            'create' => Pages\CreateReward::route('/create'),
            'edit' => Pages\EditReward::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        
        if (!$user) {
            return $query;
        }

        if ($user->hasRole('member')) {
            // العضو يرى مكافآت الأعضاء التابعين له
            return $query->whereHas('user', function ($q) use ($user) {
                $q->where('member_id', $user->id);
            });
        } elseif ($user->hasRole('member')) {
            // العضو يرى مكافآته فقط
            return $query->where('member_id', $user->id);
        }

        return $query;
    }
}