<?php

namespace App\Filament\Resources\FinancialDetailResource\Pages;

use App\Filament\Resources\FinancialDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFinancialDetail extends EditRecord
{
    protected static string $resource = FinancialDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
