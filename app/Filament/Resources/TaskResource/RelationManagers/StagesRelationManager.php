<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Models\TaskStage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StagesRelationManager extends RelationManager
{
    protected static string $relationship = 'stages';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $label = 'مرحلة';
    protected static ?string $pluralLabel = 'مراحل المهمة';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('عنوان المرحلة')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->label('وصف المرحلة')
                ->columnSpanFull(),
            Forms\Components\Select::make('status')
                ->label('الحالة')
                ->options(self::getStatusOptions())
                ->default('pending')
                ->visible(fn (): bool => $this->canManageStages()),
            Forms\Components\Hidden::make('order')
                ->default(fn ($livewire): int => $livewire->ownerRecord->stages()->count() + 1),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order')->label('الترتيب')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('العنوان')->searchable(),
                Tables\Columns\TextColumn::make('stage_number')
                    ->label('رقم المرحلة')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'gray' => 'pending',
                        'success' => 'completed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'pending' => 'قيد الانتظار',
                        'completed' => 'مكتملة',
                        default => $state
                    }),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('تاريخ الإنجاز')
                    ->dateTime()
                    ->placeholder('لم يكتمل بعد'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->visible(fn (): bool => $this->canManageStages()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (): bool => $this->canManageStages()),
                Tables\Actions\DeleteAction::make()->visible(fn (): bool => $this->canManageStages()),
                Tables\Actions\Action::make('mark_completed')
                    ->label('تحديد كمكتملة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (TaskStage $record) {
                        $record->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                        ]);
                    })
                    ->visible(fn (TaskStage $record): bool => 
                        $record->task->receiver_id === auth()->id() && 
                        $record->status === 'pending'
                    )
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->visible(fn (): bool => $this->canManageStages()),
                ]),
            ])
            ->reorderable('order');
    }

    protected function canManageStages(): bool
    {
        $task = $this->ownerRecord;
        // The owner of the team the task belongs to, or the admin can manage stages.
        return auth()->user()->hasRole('admin') || 
               ($task->team && auth()->user()->ownsTeam($task->team));
    }
}