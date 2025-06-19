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
                ->label('حالة الداعم')
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
                Tables\Columns\BadgeColumn::make('status')
                    ->label('حالة الداعم')
                    ->colors(self::getStatusColors())
                    ->formatStateUsing(fn (string $state): string => self::getStatusOptions()[$state] ?? $state),
                Tables\Columns\BadgeColumn::make('participant_status')
                    ->label('حالة المشارك')
                    ->colors(self::getParticipantStatusColors())
                    ->formatStateUsing(fn ($state): string => self::getParticipantStatusOptions()[$state] ?? 'لم يتم التحديث'),
                Tables\Columns\TextColumn::make('proof_file')
                    ->label('ملف الإثبات')
                    ->formatStateUsing(fn ($state): string => $state ? 'تم الرفع' : 'لم يتم الرفع')
                    ->url(fn ($record): ?string => $record->proof_file ? asset('storage/' . $record->proof_file) : null)
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->visible(fn (): bool => $this->canManageStages()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (): bool => $this->canManageStages()),
                Tables\Actions\DeleteAction::make()->visible(fn (): bool => $this->canManageStages()),
                Tables\Actions\Action::make('update_participant_status')
                    ->label('تحديث الحالة')
                    ->icon('heroicon-o-check-circle')
                    ->form([
                        Forms\Components\Select::make('participant_status')
                            ->label('الحالة')
                            ->options(self::getParticipantStatusOptions())
                            ->required(),
                        Forms\Components\FileUpload::make('proof_file')
                            ->label('ملف الإثبات')
                            ->disk('public')
                            ->directory('task-proofs'),
                    ])
                    ->action(fn (TaskStage $record, array $data) => $this->handleParticipantStatusUpdate($record, $data))
                    ->visible(fn (): bool => auth()->user()->hasRole('مشارك')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->visible(fn (): bool => $this->canManageStages()),
                ]),
            ])
            ->reorderable('order');
    }

    protected function handleParticipantStatusUpdate(TaskStage $record, array $data): void
    {
        $record->update([
            'participant_status' => $data['participant_status'],
            'proof_file' => $data['proof_file'] ?? $record->proof_file,
        ]);

        if ($data['participant_status'] === 'completed' && isset($data['proof_file'])) {
            $record->update(['status' => 'submitted']);
        }
    }

    protected function canManageStages(): bool
    {
        return auth()->user()->hasRole(['مدير نظام', 'داعم']);
    }

    protected static function getStatusOptions(): array
    {
        return [
            'pending' => 'معلقة',
            'submitted' => 'تم رفع الإثبات',
            'approved' => 'تم التأكد',
            'rejected' => 'مرفوضة',
            'completed' => 'مكتملة',
            'evaluated' => 'تم تقييمها',
        ];
    }

    protected static function getStatusColors(): array
    {
        return [
            'primary' => 'pending',
            'warning' => 'submitted',
            'success' => 'approved',
            'danger' => 'rejected',
            'info' => 'completed',
            'secondary' => 'evaluated',
        ];
    }

    protected static function getParticipantStatusOptions(): array
    {
        return [
            'pending' => 'قيد التنفيذ',
            'completed' => 'تم التنفيذ',
            'not_completed' => 'لم يتم التنفيذ',
        ];
    }

    protected static function getParticipantStatusColors(): array
    {
        return [
            'gray' => 'pending',
            'success' => 'completed',
            'danger' => 'not_completed',
        ];
    }
}