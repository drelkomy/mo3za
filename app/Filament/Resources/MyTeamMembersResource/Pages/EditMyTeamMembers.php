<?php

namespace App\Filament\Resources\MyTeamMembersResource\Pages;

use App\Filament\Resources\MyTeamMembersResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMyTeamMembers extends EditRecord
{
    protected static string $resource = MyTeamMembersResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
