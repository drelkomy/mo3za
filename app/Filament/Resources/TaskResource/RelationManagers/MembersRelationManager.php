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

class MembersRelationManager extends RelationManager
{
        protected static string $relationship = 'members';
    protected static ?string $title = 'الأعضاء المسؤولون عن المهمة';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('عضو الفريق')
                ->options(function () {
                    /** @var Task $task */
                    $task = $this->getOwnerRecord();
                    $team = $task->team;

                    if (!$team) {
                        return [];
                    }

                    $existingMemberIds = $task->members()->pluck('users.id')->toArray();

                    return $team->members()
                        ->where('is_active', true)
                        ->whereNotIn('users.id', $existingMemberIds)
                        ->pluck('name', 'users.id');
                })
                ->searchable()
                ->required(),
            Forms\Components\Toggle::make('is_primary')
                ->label('عضو أساسي')
                ->helperText('العضو الأساسي هو المسؤول الرئيسي عن المهمة')
                ->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم العضو')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('عضو أساسي')
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
                    ->label('إضافة عضو للمهمة')
                    ->preloadRecordSelect()
                    ->after(fn (User $record, array $data) => $this->handleParticipantAttached($record, $data)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('تعديل')
                    ->after(fn (User $record, array $data) => $this->handlePrimaryParticipantUpdate($record, $data['is_primary'])),
                Tables\Actions\DetachAction::make()
                    ->label('إزالة')
                    ->modalHeading('إزالة العضو من المهمة')
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