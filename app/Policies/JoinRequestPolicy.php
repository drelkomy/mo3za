<?php

namespace App\Policies;

use App\Models\JoinRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class JoinRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin and members can see the list of join requests.
        return $user->hasRole(['member', 'admin']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, JoinRequest $joinRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, JoinRequest $joinRequest): bool
    {
        // Admin can update any join request
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Team owner can update join requests for their team
        if ($joinRequest->team->owner_id === $user->id) {
            return true;
        }
        
        // Any team member can update join requests for teams they belong to
        if ($user->isMemberOf($joinRequest->team)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Determine whether the user can approve join requests.
     */
    public function approve(User $user, JoinRequest $joinRequest): bool
    {
        return $this->update($user, $joinRequest) && $joinRequest->status === 'pending';
    }

    /**
     * Determine whether the user can reject join requests.
     */
    public function reject(User $user, JoinRequest $joinRequest): bool
    {
        return $this->update($user, $joinRequest) && $joinRequest->status === 'pending';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, JoinRequest $joinRequest): bool
    {
        // Admin can delete any join request
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Team owner can delete requests for their team
        if ($joinRequest->team->owner_id === $user->id) {
            return true;
        }
        
        // Any team member can delete join requests for teams they belong to
        if ($user->isMemberOf($joinRequest->team)) {
            return true;
        }
        
        // User can delete their own requests
        if ($joinRequest->user_id === $user->id) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, JoinRequest $joinRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, JoinRequest $joinRequest): bool
    {
        return false;
    }
}
