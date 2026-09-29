<?php

namespace App\Policies;

use App\Models\User;
use App\Models\OrganizationConfig;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrganizationConfigPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any organization configs.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('scope:global') || 
               $user->hasAnyRole(['superadmin', 'admin', 'directeur_commercial', 'directeur_regional']);
    }

    /**
     * Determine whether the user can view the organization config.
     */
    public function view(User $user, OrganizationConfig $config): bool
    {
        // Global access
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return true;
        }

        // Check if user has access to any units in this configuration
        return $user->userScopes()
                   ->whereHas('organizationalUnit', function ($query) use ($config) {
                       $query->where('organization_config_id', $config->id);
                   })
                   ->where('is_active', true)
                   ->exists();
    }

    /**
     * Determine whether the user can create organization configs.
     */
    public function create(User $user): bool
    {
        return $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin']);
    }

    /**
     * Determine whether the user can update the organization config.
     */
    public function update(User $user, OrganizationConfig $config): bool
    {
        return $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin']);
    }

    /**
     * Determine whether the user can delete the organization config.
     */
    public function delete(User $user, OrganizationConfig $config): bool
    {
        return $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin']);
    }
}