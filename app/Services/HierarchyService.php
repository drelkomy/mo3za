<?php

namespace App\Services;

use App\Models\User;

class HierarchyService
{
    /**
     * Check if a user (manager) can manage another user (target).
     * This is now based on team ownership and membership.
     *
     * @param User $manager The user attempting to manage.
     * @param User $target The user being managed.
     * @return bool
     */
    public function canManageUser(User $manager, User $target): bool
    {
        // An admin can manage everyone.
        if ($manager->hasRole('admin')) {
            return true;
        }

        // A team owner can manage their team members.
        // We check if the target user is a member of any team owned by the manager.
        return $manager->ownedTeams()
            ->whereHas('members', function ($query) use ($target) {
                $query->where('users.id', $target->id);
            })
            ->exists();
    }
}