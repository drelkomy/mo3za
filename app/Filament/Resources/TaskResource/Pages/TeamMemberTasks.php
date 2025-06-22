<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamMemberTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;
    
        public ?User $selectedMember = null;
    
    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $team = $user->ownedTeams()->first();

        if (!$team) {
            return [];
        }

        $members = $team->members;

        return [
            Tables\Actions\Action::make('select_member')
                ->label('اختر عضو الفريق: ' . ($this->selectedMember?->name ?? 'الكل'))
                ->form([
                    \Filament\Forms\Components\Select::make('member_id')
                        ->label('عضو الفريق')
                        ->options($members->pluck('name', 'id')->prepend('جميع الأعضاء', 'all'))
                        ->default($this->selectedMember?->id ?? 'all')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->selectedMember = $data['member_id'] === 'all' ? null : User::find($data['member_id']);
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
                Tables\Columns\TextColumn::make('members.name')
                    ->label('أعضاء الفريق')
                    ->visible(fn () => !$this->selectedMember),
                Tables\Columns\BadgeColumn::make('pivot.status')
                    ->label('حالة العضو')
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
                    ->visible(fn () => $this->selectedMember),
                Tables\Columns\TextColumn::make('pivot.completion_percentage')
                    ->label('نسبة الإنجاز')
                    ->suffix('%')
                    ->visible(fn () => $this->selectedMember),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('تاريخ الاستحقاق')
                    ->date(),
                Tables\Columns\TextColumn::make('reward_amount')
                    ->label('المكافأة')
                    ->money('SAR'),
            ])
            ->actions([
                Tables\Actions\Action::make('update_member_status')
                    ->label('تحديث حالة العضو')
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
                        if ($this->selectedMember) {
                            $record->members()->updateExistingPivot($this->selectedMember->id, $data);
                        }
                    })
                    ->visible(fn () => $this->selectedMember),
            ]);
    }
    
    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        $team = $user->ownedTeams()->first();

        if (!$team) {
            return Task::query()->where('id', 0); // Return no tasks if user doesn't own a team
        }

        $query = Task::query()->where('team_id', $team->id);

        if ($this->selectedMember) {
            $query->whereHas('members', function ($q) {
                $q->where('user_id', $this->selectedMember->id);
            });
        }

        return $query;
    }
}