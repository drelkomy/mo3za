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
                        Notification::make()
                            ->title('تم رفع الإثبات بنجاح')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (TaskStage $record): bool => 
                        $record->status !== 'completed' && $this->record->receiver_id === auth()->id()
                    ),
            ])
            ->defaultSort('stage_number');
    }

    public function getTitle(): string
    {
        return "مراحل المهمة: {$this->record->title}";
    }
}