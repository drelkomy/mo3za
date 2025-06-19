<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Models\Task;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';
    protected static ?string $title = 'المشاركون في المهمة';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('المشارك')
                ->options(function () {
                    /** @var Task $task */
                    $task = $this->getOwnerRecord();
                    $existingParticipantIds = $task->participants()->pluck('users.id')->toArray();

                    return User::query()
                        ->where('supporter_id', $task->supporter_id)
                        ->where('is_active', true)
                        ->whereHas('roles', fn ($query) => $query->where('name', 'مشارك'))
                        ->whereNotIn('id', $existingParticipantIds)
                        ->pluck('name', 'id');
                })
                ->searchable()
                ->required(),
            Forms\Components\Toggle::make('is_primary')
                ->label('مشارك رئيسي')
                ->helperText('المشارك الرئيسي هو المسؤول عن المهمة')
                ->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم المشارك')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('مشارك رئيسي')
                    ->boolean(),
                Tables\Columns\TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('إضافة مشارك')
                    ->preloadRecordSelect()
                    ->after(fn (User $record, array $data) => $this->handleParticipantAttached($record, $data)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('تعديل')
                    ->after(fn (User $record, array $data) => $this->handlePrimaryParticipantUpdate($record, $data['is_primary'])),
                Tables\Actions\DetachAction::make()
                    ->label('إزالة')
                    ->modalHeading('إزالة المشارك')
                    ->before(fn (User $record) => $this->handleParticipantDetached($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label('إزالة المحدد')
                ]),
            ]);
    }
}