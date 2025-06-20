<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
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
        return auth()->user()?->hasActiveSubscription() || auth()->user()?->hasRole('admin');
    }
    
    public static function canCreate(): bool
    {
        $user = auth()->user();
        if ($user?->hasRole('admin')) return true;
        
        // يمكن للعضو إنشاء مهام إذا كان (يملك فريق أو عضو في فريق)
        return $user && ($user->ownedTeams()->exists() || $user->teams()->exists());
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
        return $user?->hasRole('admin') || 
               ($user && ($user->ownedTeams()->exists() || $user->teams()->exists()));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        if (!auth()->user()?->hasRole('admin')) {
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
                    
                    Forms\Components\Select::make('priority')
                        ->label('أولوية المهمة')
                        ->options([
                            'normal' => 'عادية',
                            'urgent' => 'عاجلة',
                        ])
                        ->default('normal')
                        ->required(),
                    
                    Forms\Components\Toggle::make('is_multiple')
                        ->label('مهمة متعددة')
                        ->default(false)
                        ->reactive(),
                    
                    Forms\Components\Select::make('selected_participants')
                        ->label('اختر المشاركين')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->options(function () {
                            $user = auth()->user();
                            if ($user?->hasRole('admin')) {
                                return \App\Models\User::pluck('name', 'id');
                            }
                            
                            // إظهار أعضاء فريقي فقط
                            $ownedTeam = $user->ownedTeams()->first();
                            if ($ownedTeam) {
                                return $ownedTeam->members()->pluck('users.name', 'users.id')->toArray();
                            }
                            
                            return [];
                        })
                        ->visible(fn ($get) => $get('is_multiple'))
                        ->required(fn ($get) => $get('is_multiple'))
                        ->placeholder('اختر عدة مشاركين'),
                ])
                ->columns(2),
            
            Forms\Components\Section::make('تفاصيل التكليف')
                ->schema([
                    Forms\Components\Select::make('receiver_id')
                        ->label('المستلم')
                        ->options(function () {
                            $user = auth()->user();
                            if ($user?->hasRole('admin')) {
                                return \App\Models\User::pluck('name', 'id');
                            }
                            
                            // إظهار أعضاء فريقي فقط
                            $ownedTeam = $user->ownedTeams()->first();
                            if ($ownedTeam) {
                                return $ownedTeam->members()->pluck('users.name', 'users.id')->toArray();
                            }
                            
                            return [];
                        })
                        ->searchable()
                        ->required(fn ($get) => !$get('is_multiple'))
                        ->visible(fn ($get) => !$get('is_multiple'))
                        ->placeholder('اختر عضو من فريقك'),
                    
                    Forms\Components\DatePicker::make('start_date')
                        ->label('تاريخ البداية')
                        ->default(now())
                        ->reactive()
                        ->required(),
                    
                    Forms\Components\TextInput::make('duration_days')
                        ->label('المدة بالأيام')
                        ->numeric()
                        ->default(7)
                        ->required()
                        ->minValue(1)
                        ->maxValue(365)
                        ->reactive()
                        ->afterStateUpdated(function ($state, $set, $get) {
                            if ($get('start_date') && is_numeric($state) && $state > 0) {
                                $start = \Carbon\Carbon::parse($get('start_date'));
                                $set('due_date', $start->copy()->addDays(intval($state))->toDateString());
                            }
                        }),
                    
                    Forms\Components\DatePicker::make('due_date')
                        ->label('تاريخ الانتهاء')
                        ->afterStateUpdated(function ($state, $set, $get) {
                            if ($get('start_date') && $state) {
                                $start = \Carbon\Carbon::parse($get('start_date'));
                                $end = \Carbon\Carbon::parse($state);
                                $days = $start->diffInDays($end);
                                $set('duration_days', $days > 0 ? $days : 1);
                            }
                        })
                        ->reactive(),
                    
                    Forms\Components\TextInput::make('total_stages')
                        ->label('عدد المراحل')
                        ->numeric()
                        ->default(3)
                        ->required()
                        ->minValue(1)
                        ->maxValue(function () {
                            $user = auth()->user();
                            $subscription = $user?->activeSubscription();
                            return $subscription?->max_milestones_per_task ?? 10;
                        })
                        ->helperText(function () {
                            $user = auth()->user();
                            $subscription = $user?->activeSubscription();
                            $max = $subscription?->max_milestones_per_task ?? 10;
                            return "الحد الأقصى المسموح: {$max} مراحل";
                        }),
                    
                    Forms\Components\Select::make('task_status')
                        ->label('حالة المهمة')
                        ->options([
                            'in_progress' => 'قيد التنفيذ',
                            'cancelled' => 'تم الإلغاء',
                            'completed' => 'انتهت المهمة',
                        ])
                        ->default('in_progress')
                        ->required(),
                    
                    Forms\Components\Select::make('reward_type')
                        ->label('نوع المكافأة')
                        ->options([
                            'cash' => 'نقدي',
                            'other' => 'أخرى',
                        ])
                        ->default('cash')
                        ->reactive()
                        ->required(),
                    
                    Forms\Components\TextInput::make('reward_amount')
                        ->label('مبلغ المكافأة')
                        ->numeric()
                        ->suffix('ريال')
                        ->visible(fn ($get) => $get('reward_type') === 'cash'),
                    
                    Forms\Components\TextInput::make('reward_description')
                        ->label('وصف المكافأة')
                        ->visible(fn ($get) => $get('reward_type') === 'other')
                        ->required(fn ($get) => $get('reward_type') === 'other'),
                ])
                ->columns(2),
            
            Forms\Components\Hidden::make('creator_id')->default(auth()->id()),
            Forms\Components\Hidden::make('status')->default('pending'),
            Forms\Components\Hidden::make('subscription_id')->default(function () {
                return auth()->user()?->activeSubscription()?->id;
            }),
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
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('priority')
                    ->label('الأولوية')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'normal' => 'عادية',
                        'urgent' => 'عاجلة',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'normal' => 'gray',
                        'urgent' => 'danger',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('progress')
                    ->label('التقدم')
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->badge()
                    ->color(fn ($state) => $state == 100 ? 'success' : ($state > 0 ? 'warning' : 'gray')),
                
                Tables\Columns\TextColumn::make('task_status')
                    ->label('حالة المهمة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in_progress' => 'قيد التنفيذ',
                        'cancelled' => 'تم الإلغاء',
                        'completed' => 'انتهت المهمة',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'in_progress' => 'warning',
                        'cancelled' => 'danger',
                        'completed' => 'success',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('start_date')
                    ->label('تاريخ البداية')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('due_date')
                    ->label('تاريخ الانتهاء')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('total_stages')
                    ->label('عدد المراحل')
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('reward_display')
                    ->label('المكافأة')
                    ->formatStateUsing(function (Task $record): string {
                        if ($record->reward_type === 'cash') {
                            return number_format($record->reward_amount, 2) . ' ريال';
                        }
                        return $record->reward_description ?? 'مكافأة أخرى';
                    })
                    ->color('success'),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label('إلغاء المهمة')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->action(fn (Task $record) => $record->update(['task_status' => 'cancelled']))
                    ->visible(fn (Task $record) => $record->creator_id === auth()->id() && $record->task_status === 'in_progress')
                    ->requiresConfirmation(),
                
                Tables\Actions\Action::make('complete')
                    ->label('إنهاء المهمة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn (Task $record) => $record->update(['task_status' => 'completed', 'completed_at' => now()]))
                    ->visible(fn (Task $record) => $record->creator_id === auth()->id() && $record->task_status === 'in_progress')
                    ->requiresConfirmation(),
                
                Tables\Actions\Action::make('reward')
                    ->label('تسليم المكافأة')
                    ->icon('heroicon-o-gift')
                    ->color('success')
                    ->action(function (Task $record) {
                        \App\Models\Reward::create([
                            'task_id' => $record->id,
                            'giver_id' => auth()->id(),
                            'receiver_id' => $record->receiver_id,
                            'amount' => $record->reward_amount,
                            'notes' => 'تم تسليم المكافأة مباشرة',
                            'status' => 'completed',
                        ]);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد تسليم المكافأة')
                    ->modalDescription(fn (Task $record) => "هل تريد تسليم مكافأة {$record->reward_amount} ريال لـ {$record->receiver->name}؟")
                    ->modalSubmitActionLabel('نعم، سلم المكافأة')
                    ->visible(function (Task $record) {
                        return $record->creator_id === auth()->id() && 
                               $record->task_status === 'completed' &&
                               !\App\Models\Reward::where('task_id', $record->id)->exists();
                    }),
                
                Tables\Actions\Action::make('view_stages')
                    ->label('عرض المراحل')
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->url(fn (Task $record): string => route('filament.admin.resources.my-tasks.stages', $record)),
                
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
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