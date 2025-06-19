<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TasksByParticipant extends ListRecords
{
    protected static string $resource = TaskResource::class;
    
    public ?User $selectedParticipant = null;
    
    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        
        if (!$user->hasRole('داعم')) {
            return [];
        }
        
        $participants = $user->participants()->get();
        
        return [
            Tables\Actions\Action::make('select_participant')
                ->label('اختر مشارك: ' . ($this->selectedParticipant?->name ?? 'الكل'))
                ->form([
                    \Filament\Forms\Components\Select::make('participant_id')
                        ->label('المشارك')
                        ->options($participants->pluck('name', 'id')->prepend('جميع المشاركين', 'all'))
                        ->default($this->selectedParticipant?->id ?? 'all')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->selectedParticipant = $data['participant_id'] === 'all' ? null : User::find($data['participant_id']);
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
                Tables\Columns\TextColumn::make('participant.name')
                    ->label('المشارك')
                    ->visible(fn () => !$this->selectedParticipant),
                Tables\Columns\BadgeColumn::make('pivot.status')
                    ->label('حالة المشارك')
                    ->color(fn (?string $state): string => match ($state) {
                        'assigned' => 'gray',
                        'in_progress' => 'info', 
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'assigned' => 'مسندة',
                        'in_progress' => 'قيد التنفيذ',
                        'completed' => 'مكتملة',
                        'cancelled' => 'ملغاة',
                        default => $state ?? 'غير محدد',
                    })
                    ->visible(fn () => $this->selectedParticipant),
                Tables\Columns\TextColumn::make('pivot.completion_percentage')
                    ->label('نسبة الإنجاز')
                    ->suffix('%')
                    ->visible(fn () => $this->selectedParticipant),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('تاريخ الاستحقاق')
                    ->date(),
                Tables\Columns\TextColumn::make('reward_amount')
                    ->label('المكافأة')
                    ->money('SAR'),
            ])
            ->actions([
                Tables\Actions\Action::make('update_participant_status')
                    ->label('تحديث حالة المشارك')
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
                        if ($this->selectedParticipant) {
                            $record->participants()->updateExistingPivot($this->selectedParticipant->id, $data);
                        }
                    })
                    ->visible(fn () => $this->selectedParticipant),
            ]);
    }
    
    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        
        if (!$user->hasRole('داعم')) {
            return Task::query()->where('id', 0);
        }
        
        $query = Task::where('supporter_id', $user->id);
        
        if ($this->selectedParticipant) {
            $query->whereHas('participants', function ($q) {
                $q->where('user_id', $this->selectedParticipant->id);
            })->with(['participants' => function ($q) {
                $q->where('user_id', $this->selectedParticipant->id);
            }]);
        }
        
        return $query;
    }
}