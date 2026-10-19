<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * @param  list<string>  $roles
     */
    public function assignRoles(User $user, ?User $target, array $roles): bool
    {
        $willHaveAdminRole = in_array('admin', $roles, true);
        $currentlyHasAdminRole = $target?->hasRole('admin', 'api') ?? false;

        if ($willHaveAdminRole === $currentlyHasAdminRole) {
            return true;
        }

        return $user->hasRole('admin', 'api');
    }
}
