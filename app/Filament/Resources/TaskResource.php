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
        if ($user?->hasRole('admin')) return false;
        
        return $user?->hasActiveSubscription() && 
               $user?->canAddTasks() && 
               $user->ownedTeams()->exists();
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
        return auth()->user()?->hasActiveSubscription() || auth()->user()?->hasRole('admin');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['receiver']);
        
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
                        ->default(3)
                        ->required()
                        ->minValue(1)
                        ->maxValue(10),
                    
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
            ])
            ->actions([
                Tables\Actions\Action::make('stages')
                    ->label('المراحل')
                    ->icon('heroicon-o-list-bullet')
                    ->url(fn (Task $record): string => route('filament.admin.resources.my-tasks.stages', $record))
                    ->visible(fn () => auth()->user()?->hasRole('admin')),
                
                Tables\Actions\Action::make('complete')
                    ->label('إنهاء المهمة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn (Task $record) => app(\App\Services\TaskService::class)->completeTaskByLeader($record))
                    ->visible(fn (Task $record) => $record->creator_id === auth()->id() && $record->status !== 'completed')
                    ->requiresConfirmation(),
                
                Tables\Actions\Action::make('reward')
                    ->label('تسليم المكافأة')
                    ->icon('heroicon-o-gift')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ')
                            ->numeric()
                            ->default(fn (Task $record) => $record->reward_amount)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات'),
                    ])
                    ->action(function (array $data, Task $record) {
                        \App\Models\Reward::create([
                            'task_id' => $record->id,
                            'giver_id' => auth()->id(),
                            'receiver_id' => $record->receiver_id,
                            'amount' => $data['amount'],
                            'notes' => $data['notes'],
                            'status' => 'pending',
                        ]);
                    })
                    ->visible(fn (Task $record) => $record->creator_id === auth()->id() && $record->status === 'completed'),
                
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // The afterCreate hook has been moved to the CreateTask page to handle the creation logic.

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'view' => Pages\ViewTask::route('/{record}'),
        ];
    }
}