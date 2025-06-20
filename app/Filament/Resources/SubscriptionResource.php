<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'الاشتراكات';
    protected static ?int $navigationSort = 4;
    protected static ?string $label = 'اشتراك';
    protected static ?string $pluralLabel = 'الاشتراكات';

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
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $package = \App\Models\Package::find($state);
                            if ($package) {
                                $set('price_paid', $package->price);
                                $set('max_tasks', $package->max_tasks);
                                $set('max_participants', $package->max_participants);
                                $set('max_milestones_per_task', $package->max_milestones_per_task);
                            }
                        }
                    })
                    ->required(),
                Forms\Components\TextInput::make('price_paid')
                    ->label('المبلغ المدفوع')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\TextInput::make('max_tasks')
                    ->label('الحد الأقصى للمهام')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\TextInput::make('max_participants')
                    ->label('الحد الأقصى للمشاركين')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\TextInput::make('max_milestones_per_task')
                    ->label('الحد الأقصى للمراحل لكل مهمة')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'نشط',
                        'inactive' => 'غير نشط',
                        'expired' => 'منتهي',
                    ])
                    ->default('active')
                    ->required(),
                Forms\Components\Hidden::make('tasks_created')->default(0),
                Forms\Components\Hidden::make('participants_created')->default(0),
                Forms\Components\Hidden::make('previous_tasks_completed')->default(0),
                Forms\Components\Hidden::make('previous_tasks_pending')->default(0),
                Forms\Components\Hidden::make('previous_rewards_amount')->default(0),
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
                Tables\Columns\TextColumn::make('package.name')
                    ->label('الباقة')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_paid')
                    ->label('المبلغ المدفوع')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_tasks')
                    ->label('المهام المتبقية')
                    ->getStateUsing(function ($record) {
                        $remaining = max(0, $record->max_tasks - $record->tasks_created);
                        return "{$remaining} / {$record->max_tasks}";
                    }),
                Tables\Columns\TextColumn::make('remaining_participants')
                    ->label('المشاركين المتبقين')
                    ->getStateUsing(function ($record) {
                        $remaining = max(0, $record->max_participants - $record->participants_created);
                        return "{$remaining} / {$record->max_participants}";
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
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}