<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;
    protected static ?string $navigationGroup = 'المحاسبة';
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'الاشتراكات';
    protected static ?int $navigationSort = 4;
    protected static ?string $label = 'اشتراك';
    protected static ?string $pluralLabel = 'الاشتراكات';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    
    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->hasRole('member');
    }
    
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if ($user->hasRole('member')) {
            return $record->status === 'active' && $record->end_date && now()->lt(\Carbon\Carbon::parse($record->end_date));
        }
        return auth()->check() && $user->hasRole('member') && $record->user->supporter_id == $user->id;
    }
    
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if ($user->hasRole('member')) {
            return $record->status === 'active' && $record->end_date && now()->lt(\Carbon\Carbon::parse($record->end_date));
        }
        return auth()->check() && $user->hasRole('member') && $record->user->supporter_id == $user->id;
    }
    
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        
        if ($user && $user->hasRole('member')) {
            // الداعم يرى فقط اشتراكاته الخاصة
            return $query->where('user_id', $user->id)->where('status', 'active')->where('end_date', '>', now());
        }
        
        // مدير النظام يرى جميع الاشتراكات
        return $query;
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin');
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('الداعم')
                    ->options(function () {
                        // الحصول على الداعمين النشطين فقط الذين ليس لديهم اشتراك نشط
                        return \App\Models\User::whereHas('roles', function ($q) {
                                $q->where('name', 'member');
                            })
                            ->where('is_active', true)
                            ->whereDoesntHave('subscriptions', function ($q) {
                                $q->where('status', 'active');
                            })
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('package_id')
                    ->label('الباقة')
                    ->options(function () {
                        return \App\Models\Package::where('is_active', true)
                            ->pluck('name', 'id');
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, $get) {
                        if ($state) {
                            $package = \App\Models\Package::find($state);
                            if ($package) {
                                $set('price_paid', $package->price);
                                $set('start_date', now());
                                $set('end_date', $package->duration_in_days ? now()->addDays($package->duration_in_days) : null);
                                // إضافة معلومات الباقة
                                $set('max_tasks', $package->max_tasks);
                                $set('max_members', $package->max_members);
                                $set('max_milestones_per_task', $package->max_milestones_per_task);
                            }
                        }
                    })
                    ->required(),
                Forms\Components\TextInput::make('max_tasks')
                    ->label('الحد الأقصى للمهام')
                    ->numeric()
                    ->default(function (callable $get) {
                        $packageId = $get('package_id');
                        if ($packageId) {
                            $package = \App\Models\Package::find($packageId);
                            return $package ? $package->max_tasks : 0;
                        }
                        return 0;
                    })
                    ->reactive(),
                Forms\Components\TextInput::make('max_members')
                    ->label('الحد الأقصى للعضوين')
                    ->numeric()
                    ->default(function (callable $get) {
                        $packageId = $get('package_id');
                        if ($packageId) {
                            $package = \App\Models\Package::find($packageId);
                            return $package ? $package->max_members : 0;
                        }
                        return 0;
                    })
                    ->reactive(),
                Forms\Components\TextInput::make('max_milestones_per_task')
                    ->label('الحد الأقصى للمراحل لكل مهمة')
                    ->numeric()
                    ->default(function (callable $get) {
                        $packageId = $get('package_id');
                        if ($packageId) {
                            $package = \App\Models\Package::find($packageId);
                            return $package ? $package->max_milestones_per_task : 0;
                        }
                        return 0;
                    })
                    ->reactive(),
                Forms\Components\TextInput::make('price_paid')
                    ->label('المبلغ المدفوع')
                    ->numeric()
                    ->default(function (callable $get) {
                        $packageId = $get('package_id');
                        if ($packageId) {
                            $package = \App\Models\Package::find($packageId);
                            return $package ? $package->price : 0;
                        }
                        return 0;
                    })
                    ->required(),
                Forms\Components\Hidden::make('status')
                    ->default('active'),
                Forms\Components\Hidden::make('tasks_created')
                    ->default(0),
                Forms\Components\Hidden::make('members_created')
                    ->default(0),
                Forms\Components\Hidden::make('previous_tasks_completed')
                    ->default(0),
                Forms\Components\Hidden::make('previous_tasks_pending')
                    ->default(0),
                Forms\Components\Hidden::make('previous_rewards_amount')
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('الداعم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('package.name')
                    ->label('الباقة')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'نشط',
                        'inactive' => 'غير نشط',
                        'expired' => 'منتهي',
                        'pending' => 'قيد الانتظار',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'expired' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط؟')
                    ->boolean()
                    ->getStateUsing(function ($record) {
                        return $record->status === 'active' && 
                               $record->end_date && 
                               now()->lt(\Carbon\Carbon::parse($record->end_date));
                    }),
                Tables\Columns\TextColumn::make('remaining_tasks')
                    ->label('المهام المتبقية')
                    ->getStateUsing(function ($record) {
                        if (!$record->user) {
                            return 'N/A';
                        }
                        $usedTasks = $record->user->createdTasks()
                            ->where('created_at', '>=', $record->start_date)
                            ->count();
                        $remaining = max(0, $record->max_tasks - $usedTasks);
                        return "{$remaining} / {$record->max_tasks}";
                    }),
                Tables\Columns\TextColumn::make('remaining_members')
                    ->label('العضوين المتبقين')
                    ->getStateUsing(function ($record) {
                        if (!$record->user) {
                            return 'N/A';
                        }
                        $usedParticipants = $record->user->members()
                            ->where('is_active', true)
                            ->count();
                        $remaining = max(0, $record->max_members - $usedParticipants);
                        return "{$remaining} / {$record->max_members}";
                    }),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('تاريخ البدء')
                    ->date('Y-m-d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('تاريخ الانتهاء')
                    ->date('Y-m-d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment.amount')
                    ->label('السعر')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('تاريخ التحديث')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('تجديد الاشتراك')
                    ->color('success')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $endDate = $record->end_date ? \Carbon\Carbon::parse($record->end_date) : now();
                        $record->update([
                            'end_date' => $endDate->addMonth(),
                            'status' => 'active',
                        ]);
                        $record->user->update(['is_active' => true]);
                        $record->user->members()->update(['is_active' => true]);
                    })
                    ->visible(function($record){
                        $isActive = $record->status === 'active' && 
                                   $record->end_date && 
                                   now()->lt(\Carbon\Carbon::parse($record->end_date));
                        return !$isActive;
                    }),

                Tables\Actions\Action::make('إلغاء تفعيل الاشتراك')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->status = 'inactive';
                        $record->save();

                        if ($record->user) {
                            $record->user->members()->update(['is_active' => false]);
                        }
                    })
                    ->visible(function($record){
                        return $record->status === 'active' && 
                               $record->end_date && 
                               now()->lt(\Carbon\Carbon::parse($record->end_date));
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasRole('member')),
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