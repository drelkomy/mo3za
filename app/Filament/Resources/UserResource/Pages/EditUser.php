<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }


    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['user_type'] = $this->record->roles->first()?->name;
 
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (isset($data['user_type'])) {
            $userType = $data['user_type'];
            unset($data['user_type']);
            $record->syncRoles([$userType]);
        }

        // If password is blank, don't update it.
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $record->update($data);

        return $record;
    }
}
