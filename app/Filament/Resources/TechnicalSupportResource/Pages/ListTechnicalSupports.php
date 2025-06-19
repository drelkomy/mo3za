<?php

namespace App\Filament\Resources\TechnicalSupportResource\Pages;

use App\Filament\Resources\TechnicalSupportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTechnicalSupports extends ListRecords
{
    protected static string $resource = TechnicalSupportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
