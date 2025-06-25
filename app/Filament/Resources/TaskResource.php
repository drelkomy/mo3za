<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Filament\Resources\TaskResource\RelationManagers;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;
    protected static ?string $navigationGroup = 'إدارة الفرق';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'مهام الفريق';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        if ($user->hasRole('admin')) {
            return true;
        }
        // Show if user has an active subscription, has created tasks, or has received tasks.
        $subscription = $user->activeSubscription;
        return ($subscription && $subscription->status === 'active') || $user->createdTasks()->exists() || $user->receivedTasks()->exists();
    }
    
    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // The canAddTasks method handles subscription checks. We also need to ensure the user owns a team.
        return $user->canAddTasks() && $user->ownedTeams()->exists();
    }
    
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
    
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        if ($user->hasRole('admin')) {
            return true;
        }
        // Show navigation item if user has an active subscription, has created tasks, or has received tasks.
        $subscription = $user->activeSubscription;
        return ($subscription && $subscription->status === 'active') || $user->createdTasks()->exists() || $user->receivedTasks()->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        if (auth()->check() && !auth()->user()->hasRole('admin')) {
            // عرض المهام التي أنشأها الشخص فقط
            $query->where('creator_id', auth()->id());
        }
        
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('تفاصيل المهمة')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('اسم المهمة')
                        ->required()
                        ->maxLength(255),
                    
                    Forms\Components\RichEditor::make('description')
                        ->label('وصف المهمة')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(1),
            
            Forms\Components\Section::make('تفاصيل التكليف')
                ->schema([
                    Forms\Components\Select::make('receiver_ids')
                        ->label('المستلمون')
                        ->multiple()
                        ->options(function () {
                            $user = auth()->user();
                            if ($user?->hasRole('admin')) {
                                return \App\Models\User::pluck('name', 'id');
                            }
                            
                            $team = $user->ownedTeams()->first();
                            if ($team) {
                                return $team->members()->pluck('users.name', 'users.id');
                            }
                            
                            return [];
                        })
                        ->searchable()
                        ->required()
                        ->placeholder('اختر عضو أو أكثر من فريقك'),
                    
                    Forms\Components\DatePicker::make('start_date')
                        ->label('تاريخ البداية')
                        ->default(now())
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (callable $set, callable $get, $state) {
                            if ($get('duration_days') && $state) {
                                $set('due_date', now()->parse($state)->addDays((int) $get('duration_days'))->toDateString());
                            }
                        }),
                    
                    Forms\Components\TextInput::make('duration_days')
                        ->label('المدة بالأيام')
                        ->numeric()
                        ->default(7)
                        ->required()
                        ->minValue(1)
                        ->maxValue(365)
                        ->reactive()
                        ->afterStateUpdated(function (callable $set, callable $get, $state) {
                            if ($get('start_date') && $state) {
                                $set('due_date', now()->parse($get('start_date'))->addDays((int) $state)->toDateString());
                            }
                        }),
                    
                    Forms\Components\DatePicker::make('due_date')
                        ->label('تاريخ النهاية')
                        ->disabled()
                        ->dehydrated(false),
                    
                    Forms\Components\TextInput::make('total_stages')
                        ->label('عدد المراحل')
                        ->numeric()
                        ->default(function() {
                            $user = auth()->user();
                            $subscription = $user?->activeSubscription;
                            return min(3, $subscription?->package?->max_milestones_per_task ?? 3);
                        })
                        ->required()
                        ->minValue(1)
                        ->maxValue(function() {
                            $user = auth()->user();
                            $subscription = $user?->activeSubscription;
                            return $subscription?->package?->max_milestones_per_task ?? 10;
                        }),
                    
                    Forms\Components\Select::make('reward_type')
                        ->label('نوع المكافأة')
                        ->options([
                            'cash' => 'نقدي',
                            'other' => 'أخرى',
                        ])
                        ->default('cash')
                        ->required()
                        ->reactive(),
                    
                    Forms\Components\TextInput::make('reward_amount')
                        ->label('مبلغ المكافأة')
                        ->numeric()
                        ->suffix('ريال')
                        ->visible(fn ($get) => $get('reward_type') === 'cash'),
                    
                    Forms\Components\TextInput::make('reward_description')
                        ->label('وصف المكافأة')
                        ->visible(fn ($get) => $get('reward_type') === 'other'),
                ])
                ->columns(2),
            
            Forms\Components\Hidden::make('creator_id')->default(auth()->id()),
            Forms\Components\Hidden::make('status')->default('pending'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('اسم المهمة')
                    ->searchable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('receiver.name')
                    ->label('المستلم')
                    ->badge()
                    ->color('info')
                    ->default('غير محدد')
                    ->formatStateUsing(fn ($state) => $state ?? 'غير محدد'),
                
                Tables\Columns\TextColumn::make('progress')
                    ->label('التقدم')
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->badge()
                    ->color(fn ($state) => $state == 100 ? 'success' : ($state > 0 ? 'warning' : 'gray')),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'قيد الانتظار',
                        'in_progress' => 'قيد التنفيذ',
                        'completed' => 'مكتملة',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('start_date')
                    ->label('تاريخ البداية')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('due_date')
                    ->label('تاريخ النهاية')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('reward_type')
                    ->label('نوع المكافأة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'نقدي',
                        'other' => 'أخرى',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'other' => 'warning',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('reward_amount')
                    ->label('مبلغ المكافأة')
                    ->money('SAR')
                    ->color('success')
                    ->placeholder('لا يوجد'),
                
                Tables\Columns\TextColumn::make('reward_description')
                    ->label('وصف المكافأة')
                    ->limit(30)
                    ->color('warning')
                    ->placeholder('لا يوجد'),
                Tables\Columns\TextColumn::make('subscription.status')
                    ->label('حالة الاشتراك')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'cancelled' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subscription_status')
                    ->label('حالة الاشتراك')
                    ->options([
                        'active' => 'نشط',
                        'expired' => 'منتهي',
                        'cancelled' => 'ملغى',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            return $query->whereHas('subscription', function (Builder $q) use ($data) {
                                $q->where('status', $data['value']);
                            });
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('stages')
                    ->label('المراحل')
                    ->icon('heroicon-o-list-bullet')
                    ->modalHeading('مراحل المهمة')
                    ->modalContent(function (Task $record) {
                        $stages = $record->stages()->with('media')->get();
                        return view('filament.components.task-stages', ['stages' => $stages, 'task' => $record]);
                    })
                    ->modalWidth('4xl'),
                
                Tables\Actions\Action::make('complete')
                    ->label('إغلاق المهمة وتسليم الجائزة')
                    ->icon('heroicon-o-gift')
                    ->color('success')
                    ->action(function (Task $record) {
                        $record->update(['status' => 'completed', 'progress' => 100]);
                        
                        // تحديث جميع المراحل إلى مكتملة
                        $record->stages()->update(['status' => 'completed']);
                        
                        // إنشاء المكافأة
                        if ($record->reward_type === 'cash' && $record->reward_amount > 0) {
                            \App\Models\Reward::create([
                                'task_id' => $record->id,
                                'giver_id' => $record->creator_id,
                                'receiver_id' => $record->receiver_id,
                                'amount' => $record->reward_amount,
                                'status' => 'completed',
                                'notes' => 'مكافأة مهمة: ' . $record->title,
                            ]);
                        } elseif ($record->reward_type === 'other' && $record->reward_description) {
                            \App\Models\Reward::create([
                                'task_id' => $record->id,
                                'giver_id' => $record->creator_id,
                                'receiver_id' => $record->receiver_id,
                                'amount' => 0,
                                'status' => 'completed',
                                'notes' => 'مكافأة مهمة: ' . $record->title,
                            ]);
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('تم إغلاق المهمة وتسليم الجائزة')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Task $record) => $record->creator_id === auth()->id() && $record->status !== 'completed')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد إغلاق المهمة')
                    ->modalDescription('هل أنت متأكد من إغلاق هذه المهمة وتسليم الجائزة للمستلم؟'),
                
                Tables\Actions\Action::make('attachments')
                    ->label('المرفقات')
                    ->icon('heroicon-o-paper-clip')
                    ->color('info')
                    ->modalHeading('مرفقات إثبات إنجاز المهمة')
                    ->modalContent(function (Task $record) {
                        $attachments = $record->getMedia('payment_proofs');
                        if ($attachments->isEmpty()) {
                            return view('filament.components.no-attachments');
                        }
                        return view('filament.components.task-attachments', ['attachments' => $attachments]);
                    })
                    ->modalWidth('2xl')
                    ->visible(fn (Task $record) => $record->getMedia('task_attachments')->isNotEmpty()),
                
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([5])
            ->defaultPaginationPageOption(5);
    }

    // The afterCreate hook has been moved to the CreateTask page to handle the creation logic.

    public static function getRelations(): array
    {
        return [
            RelationManagers\MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'view' => Pages\ViewTask::route('/{record}'),
        ];
    }
}
