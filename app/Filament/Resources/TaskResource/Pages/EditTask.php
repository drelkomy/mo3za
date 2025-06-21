<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use App\Models\Task;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\RepeatableEntry;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                RepeatableEntry::make('stages')
                    ->label('مراحل المهمة')
                    ->schema([
                        TextEntry::make('stage_number')->label('رقم المرحلة')->badge(),
                        TextEntry::make('title')->label('العنوان'),
                        TextEntry::make('status')
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
                        TextEntry::make('completed_at')->label('تاريخ الإكمال')->dateTime(),
                        TextEntry::make('proof_notes')->label('ملاحظات الإثبات'),
                        SpatieMediaLibraryImageEntry::make('proof_attachments')
                            ->label('مرفقات الإثبات')
                            ->collection('proofs')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
            ]);
    }
}
