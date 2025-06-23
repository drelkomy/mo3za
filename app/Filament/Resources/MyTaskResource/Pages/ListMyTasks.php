<?php

namespace App\Filament\Resources\MyTaskResource\Pages;

use App\Filament\Resources\MyTaskResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Models\Task;

class ListMyTasks extends ListRecords
{
    protected static string $resource = MyTaskResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان المهمة')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'قيد الانتظار',
                        'in_progress' => 'قيد التنفيذ',
                        'completed' => 'مكتملة',
                        'not_completed' => 'لم تكتمل',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'in_progress' => 'info',
                        'completed' => 'success',
                        'not_completed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('progress')
                    ->label('التقدم')
                    ->formatStateUsing(fn ($state) => "{$state}%")
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('تاريخ الإكمال')
                    ->dateTime(),
            ])
            ->filters([
                // Add filters if needed
            ])
            ->actions([
                Tables\Actions\Action::make('view_stages')
                    ->label('عرض المراحل')
                    ->url(fn (Task $record): string => MyTaskResource::getUrl('stages', ['record' => $record])),
                Tables\Actions\Action::make('close_task')
                    ->label('إغلاق المهمة')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Select::make('status')
                            ->label('حالة المهمة')
                            ->options([
                                'completed' => 'تم الإنجاز',
                                'not_completed' => 'لم يتم الإنجاز',
                            ])
                            ->required(),
                        \Filament\Forms\Components\Toggle::make('distribute_reward')
                            ->label('توزيع المكافأة')
                            ->visible(fn ($get, Task $record) => $get('status') === 'completed' && $record->reward_amount > 0),
                    ])
                    ->action(function (Task $record, array $data): void {
                        $taskService = app(\App\Services\TaskService::class);
                        if ($data['status'] === 'completed') {
                            $taskService->completeTaskByLeader($record, $data['distribute_reward'] ?? false);
                            Notification::make()
                                ->title('تم إغلاق المهمة بنجاح')
                                ->body('تم إنجاز المهمة' . ($data['distribute_reward'] ? ' مع توزيع المكافأة.' : ' بدون توزيع المكافأة.'))
                                ->success()
                                ->send();
                        } else {
                            $record->update([
                                'status' => 'not_completed',
                                'completed_at' => now(),
                            ]);
                            Notification::make()
                                ->title('تم إغلاق المهمة بنجاح')
                                ->body('تم إغلاق المهمة بدون إنجاز.')
                                ->success()
                                ->send();
                        }
                    })
                    ->visible(fn (Task $record): bool => $record->creator_id === auth()->id()),
            ])
            ->bulkActions([
                // Add bulk actions if needed
            ]);
    }
}
