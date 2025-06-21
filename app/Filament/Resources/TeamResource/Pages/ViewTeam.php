<?php

namespace App\Filament\Resources\TeamResource\Pages;

use App\Filament\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewTeam extends ViewRecord
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn() => $this->record->owner_id === auth()->id()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('معلومات الفريق')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('اسم الفريق')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                        Infolists\Components\TextEntry::make('owner.name')
                            ->label('مالك الفريق'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('تاريخ الإنشاء')
                            ->dateTime(),
                    ]),
                
                Infolists\Components\Section::make('أعضاء الفريق')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('members')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('الاسم'),
                                Infolists\Components\TextEntry::make('email')
                                    ->label('البريد الإلكتروني'),
                                Infolists\Components\TextEntry::make('pivot.created_at')
                                    ->label('تاريخ الانضمام')
                                    ->dateTime(),
                            ])
                            ->columns(3),
                    ])
                    ->collapsible(),
            ]);
    }
}