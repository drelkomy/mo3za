<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNotifications extends ListRecords
{
    protected static string $resource = NotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('mark_all_read')
                ->label('تحديد الكل كمقروء')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function () {
                    auth()->user()->notifications()->unread()->update(['read_at' => now()]);
                }),
        ];
    }
}