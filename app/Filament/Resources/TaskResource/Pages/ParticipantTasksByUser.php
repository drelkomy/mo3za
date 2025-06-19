<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParticipantTasksByUser extends ListRecords
{
    protected static string $resource = TaskResource::class;
    
    public ?User $selectedParticipant = null;
    
    public function mount(): void
    {
        parent::mount();
        
        // Get first participant if none selected
        if (!$this->selectedParticipant) {
            $user = auth()->user();
            if ($user->hasRole('داعم')) {
                $this->selectedParticipant = $user->participants()->first();
            }
        }
    }
    
    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        
        if (!$user->hasRole('داعم')) {
            return [];
        }
        
        return [
            Tables\Actions\Action::make('select_participant')
                ->label('اختر مشارك')
                ->form([
                    \Filament\Forms\Components\Select::make('participant_id')
                        ->label('المشارك')
                        ->options($user->participants()->pluck('name', 'id'))
                        ->default($this->selectedParticipant?->id)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->selectedParticipant = User::find($data['participant_id']);
                }),
        ];
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان المهمة')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('pivot.status')
                    ->label('حالة المشارك')
                    ->color(fn (string $state): string => match ($state) {
                        'assigned' => 'gray',
                        'in_progress' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'assigned' => 'مسندة',
                        'in_progress' => 'قيد التنفيذ',
                        'completed' => 'مكتملة',
                        'cancelled' => 'ملغاة',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('pivot.completion_percentage')
                    ->label('نسبة الإنجاز')
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('تاريخ الاستحقاق')
                    ->date(),
                Tables\Columns\TextColumn::make('reward_amount')
                    ->label('المكافأة')
                    ->money('SAR'),
            ])
            ->actions([
                Tables\Actions\Action::make('update_status')
                    ->label('تحديث الحالة')
                    ->form([
                        \Filament\Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'assigned' => 'مسندة',
                                'in_progress' => 'قيد التنفيذ',
                                'completed' => 'مكتملة',
                                'cancelled' => 'ملغاة',
                            ])
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('completion_percentage')
                            ->label('نسبة الإنجاز')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات'),
                    ])
                    ->action(function (Task $record, array $data) {
                        $record->participants()->updateExistingPivot($this->selectedParticipant->id, $data);
                    })
                    ->visible(fn () => $this->selectedParticipant),
            ]);
    }
    
    protected function getTableQuery(): Builder
    {
        if (!$this->selectedParticipant) {
            return Task::query()->where('id', 0); // Empty result
        }
        
        return $this->selectedParticipant->participatedTasks()->getQuery();
    }
}