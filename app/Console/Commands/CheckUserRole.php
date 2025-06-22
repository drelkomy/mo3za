<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CheckUserRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:check-role {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the user_type field and Spatie roles for a given user.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        $this->info("--- User Diagnostic Report for {$email} ---");

        // 1. Check the 'user_type' column directly from the database attribute
        $userTypeFromDb = $user->getAttributes()['user_type'] ?? 'Not Set';
        $this->info("Database -> 'user_type' column: [{$userTypeFromDb}]");

        // 2. Check the roles assigned via Spatie
        $spatieRoles = $user->getRoleNames()->implode(', ');
        $this->info("Spatie Roles: [" . ($spatieRoles ?: 'None') . "]");

        // 3. Check all permissions (direct and via roles)
        $permissions = $user->getAllPermissions()->pluck('name')->implode(', ');
        $this->info("Spatie Permissions: [" . ($permissions ?: 'None') . "]");

        // 4. Check for the specific permission
        $hasSpecificPermission = $user->can('view join requests');
        $this->info("Can 'view join requests'?: [" . ($hasSpecificPermission ? 'Yes' : 'No') . "]");

        if ($userTypeFromDb === 'member' && !empty($spatieRoles) && $hasSpecificPermission) {
            $this->info("\nConclusion: The user appears to be configured correctly for viewing join requests.");
        } else {
            $this->error("\nConclusion: There is a configuration issue. The user may be missing the role or the required permission.");
        }

        return 0;
    }
}
