<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = static::getModel()::create($data);

        // Explicitly assign the role for the Filament context
        if (isset($data['user_type'])) {
            $user->assignRole($data['user_type']);
        }

        // Reset the permission cache to ensure Filament recognizes the new role immediately
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $user;
    }
}
