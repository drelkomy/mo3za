<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use App\Models\Milestone;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';

    public function getRelationshipQuery(): Builder
    {
        $query = parent::getRelationshipQuery();
        $user = auth()->user();

        // If the user is a participant, they should only see their own milestones.
        if ($user->hasRole('مشارك')) {
            $query->where('participant_id', $user->id);
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('المرحلة'),
                Tables\Columns\TextColumn::make('description')->label('الوصف')->limit(50),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()->color(fn (string $state): string => match ($state) {
                    'pending' => 'gray',
                    'in_review' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('participant.name')
                    ->label('المشارك')
                    ->default('(غير محدد)')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => auth()->user()->hasRole(['داعم', 'مدير نظام'])),
                Tables\Columns\IconColumn::make('proof_file_path')
                    ->label('الإثبات')
                    ->icon('heroicon-o-paper-clip')
                    ->url(fn (Milestone $record) => $record->proof_file_path ? Storage::url($record->proof_file_path) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (?Milestone $record) => !empty($record->proof_file_path)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Action::make('submit_for_review')
                    ->label('تسليم للمراجعة')
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->color('primary')
                    ->action(function (Milestone $record, array $data): void {
                        $record->update([
                            'proof_file_path' => $data['proof_file_path'],
                            'status' => 'in_review',
                        ]);
                    })
                    ->visible(function (Milestone $record): bool {
                        // Visible only to the assigned participant if the milestone is pending or rejected.
                        return auth()->id() === $record->participant_id && in_array($record->status, ['pending', 'rejected']);
                    })
                    ->form([
                        FileUpload::make('proof_file_path')
                            ->label('ملف الإثبات (صورة، فيديو، مستند)')
                            ->required()
                            ->disk('public')
                            ->directory('milestone-proofs')
                            ->acceptedFileTypes(['image/*', 'video/*', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']),
                    ]),

                Action::make('approve')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Milestone $record): void {
                        DB::transaction(function () use ($record) {
                            $record->update(['status' => 'approved']);

                            // Check if this was the last milestone for this participant in this task
                            $remainingMilestones = Milestone::where('task_id', $record->task_id)
                                ->where('participant_id', $record->participant_id)
                                ->where('status', '!=', 'approved')
                                ->count();

                            if ($remainingMilestones === 0) {
                                // Award the prize
                                $task = $record->task;
                                if ($task->reward_amount > 0) {
                                    $task->rewards()->create([
                                        'user_id' => $record->participant_id,
                                        'amount' => $task->reward_amount,
                                        'description' => $task->reward_description ?? 'مكافأة إنجاز المهمة: ' . $task->name,
                                        'type' => 'points',
                                        'status' => 'completed',
                                        'awarded_at' => now(),
                                    ]);

                                    Notification::make()
                                        ->title('تم منح المكافأة!')
                                        ->body('لقد أكمل المشارك ' . $record->participant->name . ' جميع مراحل المهمة بنجاح وحصل على المكافأة.')
                                        ->success()
                                        ->sendToDatabase(auth()->user());
                                }
                            }
                        });
                    })
                    ->visible(fn (Milestone $record) => auth()->user()->hasRole('داعم') && $record->status === 'in_review'),

                Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-hand-thumb-down')
                    ->color('danger')
                    ->action(function (Milestone $record, array $data): void {
                        if ($record->proof_file_path) {
                            Storage::disk('public')->delete($record->proof_file_path);
                        }
                        $record->update([
                            'status' => 'pending', // Revert to pending to allow re-submission
                            'rejection_comment' => $data['rejection_comment'],
                            'proof_file_path' => null,
                        ]);
                    })
                    ->visible(fn (Milestone $record): bool => auth()->user()->hasRole('داعم') && $record->status === 'in_review')
                    ->form([
                        Textarea::make('rejection_comment')
                            ->label('سبب الرفض')
                            ->required(),
                    ]),
            ])
            ->bulkActions([
                //
            ]);
    }
}
