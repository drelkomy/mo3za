<?php

namespace App\Filament\Resources\TechnicalSupportResource\Pages;

use App\Filament\Resources\TechnicalSupportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTechnicalSupport extends EditRecord
{
    protected static string $resource = TechnicalSupportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
