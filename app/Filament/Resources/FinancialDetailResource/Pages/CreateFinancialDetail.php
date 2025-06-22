<?php

namespace App\Filament\Resources\FinancialDetailResource\Pages;

use App\Filament\Resources\FinancialDetailResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinancialDetail extends CreateRecord
{
    protected static string $resource = FinancialDetailResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        return $data;
    }
}