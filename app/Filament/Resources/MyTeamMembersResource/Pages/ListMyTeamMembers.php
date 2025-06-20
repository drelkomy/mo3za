<?php

namespace App\Filament\Resources\MyTeamMembersResource\Pages;

use App\Filament\Resources\MyTeamMembersResource;
use Filament\Resources\Pages\ListRecords;

class ListMyTeamMembers extends ListRecords
{
    protected static string $resource = MyTeamMembersResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}