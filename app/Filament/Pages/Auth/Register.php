<?php

namespace App\Filament\Pages\Auth;

use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Filament\Pages\Auth\Register as BaseRegister;
use Spatie\Permission\PermissionRegistrar;


class Register extends BaseRegister
{
    public function register(): ?RegistrationResponse
    {
        $response = parent::register();

        if ($response) {
            // Get the user that was just created and logged in.
            $user = Filament::auth()->user();
            
            if ($user) {
                // IMPORTANT: We find the user again from the DB to get a fresh instance.
                $freshUser = \App\Models\User::find($user->id);

                // Assign the role to the fresh instance.
                $freshUser->assignRole('member');

                // Clear the permission cache system-wide.
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                
                // Log the fresh user instance back in to update the session.
                Filament::auth()->login($freshUser);
            }
        }

        return $response;
    }
}
