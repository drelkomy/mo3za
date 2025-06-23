<?php

namespace App\Filament\Resources\MyTaskResource\Pages;

use App\Filament\Resources\MyTaskResource;
use App\Models\Task;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Infolists;
use App\Models\TaskStage;
use Filament\Notifications\Notification;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;

class TaskStages extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MyTaskResource::class;
    protected static string $view = 'filament.resources.my-task-resource.pages.task-stages';

    public Task $record;

    public function mount(Task $record): void
    {
        $this->record = $record;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->check();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->record->stages()->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('stage_number')
                    ->label('رقم المرحلة')
                    ->badge()
                    ->color('primary'),
                
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان المرحلة')
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'قيد الانتظار',
                        'completed' => 'مكتملة',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('تاريخ الإكمال')
                    ->dateTime(),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('ملاحظات الإثبات')
                    ->limit(50),
                
                Tables\Columns\TextColumn::make('proof_link')
                    ->label('رابط الإثبات')
                    ->formatStateUsing(function (?TaskStage $record) {
                        if (!$record) return __('لا يوجد ملف');
                        $media = $record->getMedia('proofs');
                        return $media->isNotEmpty() ? __('تحميل') : __('لا يوجد ملف');
                    })
                    ->url(function (?TaskStage $record) {
                        if (!$record) return null;
                        $media = $record->getMedia('proofs');
                        return $media->isNotEmpty() ? $media->last()->getUrl() : null;
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (?TaskStage $record): bool => $record && $record->getMedia('proofs')->isNotEmpty() && ($this->record->creator_id === auth()->id() || $this->record->receiver_id === auth()->id())),
            ])
            ->actions([
                Tables\Actions\Action::make('complete_stage')
                    ->label('إنجاز المرحلة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (TaskStage $record) {
                        $record->markAsCompleted();
                        Notification::make()
                            ->title('تم إنجاز المرحلة بنجاح')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (TaskStage $record): bool => 
                        $record->status === 'pending' && $this->record->receiver_id === auth()->id()
                    ),

                Tables\Actions\Action::make('upload_proof')
                    ->label('رفع إثبات')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->form([
                        Textarea::make('description')
                            ->label('ملاحظات الإثبات'),
                        SpatieMediaLibraryFileUpload::make('proof_attachments') // A temporary key
                            ->label('المرفقات')
                            ->collection('proofs') // Match the collection name
                            ->multiple()
                            ->maxFiles(5),
                    ])
                    ->action(function (TaskStage $record, array $data): void {
                        $record->update(['description' => $data['description']]);
                        // The Spatie component will handle the file uploads automatically.
                        
                        // Create or update StageCompletion record
                        $completion = \App\Models\StageCompletion::updateOrCreate(
                            ['task_stage_id' => $record->id, 'user_id' => auth()->id()],
                            ['status' => 'pending', 'notes' => $data['description']]
                        );
                        
                        // Get the uploaded media files
                        $media = $record->getMedia('proofs');
                        if ($media->isNotEmpty()) {
                            $latestMedia = $media->last();
                            $completion->update([
                                'proof_path' => $latestMedia->getPath(),
                                'proof_type' => $latestMedia->mime_type,
                            ]);
                        }
                        
                        Notification::make()
                            ->title('تم رفع الإثبات بنجاح')
                            ->body('يمكنك تحميل الإثبات من خلال الرابط التالي: ' . ($media->isNotEmpty() ? $latestMedia->getUrl() : 'لا يوجد ملف مرفق'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (TaskStage $record): bool => 
                        $record->status !== 'completed' && $this->record->receiver_id === auth()->id()
                    ),

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
                            ->visible(fn ($get) => $get('status') === 'completed' && $this->record->reward_amount > 0),
                    ])
                    ->action(function (array $data): void {
                        $taskService = app(\App\Services\TaskService::class);
                        if ($data['status'] === 'completed') {
                            $taskService->completeTaskByLeader($this->record, $data['distribute_reward'] ?? false);
                            Notification::make()
                                ->title('تم إغلاق المهمة بنجاح')
                                ->body('تم إنجاز المهمة' . ($data['distribute_reward'] ? ' مع توزيع المكافأة.' : ' بدون توزيع المكافأة.'))
                                ->success()
                                ->send();
                        } else {
                            $this->record->update([
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
                    ->visible(fn (): bool => $this->record->creator_id === auth()->id()),
            ])
            ->defaultSort('stage_number');
    }

    public function getTitle(): string
    {
        return "مراحل المهمة: {$this->record->title}";
    }
}
