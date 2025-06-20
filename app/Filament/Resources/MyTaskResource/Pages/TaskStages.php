<?php

namespace App\Filament\Resources\MyTaskResource\Pages;

use App\Filament\Resources\MyTaskResource;
use App\Models\Task;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class TaskStages extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MyTaskResource::class;
    protected static string $view = 'filament.resources.my-task-resource.pages.task-stages';

    public Task $record;

    public function mount(Task $record): void
    {
        $this->record = $record;
        
        // التأكد من أن المستخدم هو المستلم أو منشئ المهمة
        if ($record->receiver_id !== auth()->id() && $record->creator_id !== auth()->id()) {
            abort(403);
        }
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
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Riyadh'),
                
                Tables\Columns\TextColumn::make('attachments')
                    ->label('المرفقات')
                    ->formatStateUsing(function ($state, $record) {
                        if (empty($state)) {
                            return '<span class="text-gray-500">لا يوجد</span>';
                        }
                        
                        $files = explode(',', $state);
                        $links = [];
                        
                        foreach ($files as $file) {
                            $fileName = basename($file);
                            $downloadUrl = url('/download-attachment/' . urlencode($file));
                            $links[] = '<a href="' . $downloadUrl . '" class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-primary-600 rounded hover:bg-primary-700" target="_blank">تحميل ' . $fileName . '</a>';
                        }
                        
                        return '<div class="space-y-1">' . implode('', $links) . '</div>';
                    })
                    ->html(),
            ])
            ->actions([
                Tables\Actions\Action::make('complete')
                    ->label('تم الإنجاز')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\FileUpload::make('attachments')
                            ->label('مرفقات (اختياري)')
                            ->multiple()
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('task-attachments')
                            ->helperText('يمكنك رفع صور أو ملفات PDF'),
                    ])
                    ->action(function (array $data, $record) {
                        $record->markAsCompleted();
                        
                        // حفظ مسارات المرفقات
                        if (!empty($data['attachments'])) {
                            $attachmentPaths = implode(',', $data['attachments']);
                            $record->update(['attachments' => $attachmentPaths]);
                        }
                    })
                    ->visible(fn ($record) => $record->status === 'pending' && $record->task->receiver_id === auth()->id()),
                
                Tables\Actions\Action::make('download')
                    ->label('تحميل')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(function ($record) {
                        if (empty($record->attachments)) {
                            return null;
                        }
                        $files = explode(',', $record->attachments);
                        return url('/download-attachment/' . urlencode($files[0]));
                    })
                    ->openUrlInNewTab()
                    ->visible(function ($record) {
                        return !empty($record->attachments) && 
                               ($record->task->creator_id === auth()->id() || $record->task->receiver_id === auth()->id());
                    }),
                

            ])
            ->defaultSort('stage_number');
    }

    public function getTitle(): string
    {
        return "مراحل المهمة: {$this->record->title}";
    }
}