<?php

namespace App\Filament\Resources\JoinRequestResource\Pages;

use App\Filament\Resources\JoinRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJoinRequest extends EditRecord
{
    protected static string $resource = JoinRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
