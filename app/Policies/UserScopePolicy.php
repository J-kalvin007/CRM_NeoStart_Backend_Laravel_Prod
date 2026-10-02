<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserScope;
use App\Services\PerimeterService;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserScopePolicy
{
    use HandlesAuthorization;

    protected PerimeterService $perimeterService;

    public function __construct(PerimeterService $perimeterService)
    {
        $this->perimeterService = $perimeterService;
    }

    /**
     * Determine whether the user can view any user scopes.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view scopes (filtered in controller)
    }

    /**
     * Determine whether the user can view the user scope.
     */
    public function view(User $user, UserScope $scope): bool
    {
        // Users can always view their own scopes
        if ($scope->user_id === $user->id) {
            return true;
        }

        // Check if user can manage other users
        return $this->perimeterService->canManageUsers($user);
    }

    /**
     * Determine whether the user can create user scopes.
     */
    public function create(User $user): bool
    {
        return $this->perimeterService->canManageUsers($user);
    }

    /**
     * Determine whether the user can update the user scope.
     */
    public function update(User $user, UserScope $scope): bool
    {
        // Users can update their own scopes
        if ($scope->user_id === $user->id) {
            return true;
        }

        // Check if user can manage other users
        return $this->perimeterService->canManageUsers($user);
    }

    /**
     * Determine whether the user can delete the user scope.
     */
    public function delete(User $user, UserScope $scope): bool
    {
        // Users can delete their own scopes (unless it's their only scope)
        if ($scope->user_id === $user->id) {
            $userScopesCount = UserScope::where('user_id', $user->id)
                ->where('is_active', true)
                ->count();
            
            return $userScopesCount > 1; // Don't allow deleting the last scope
        }

        // Check if user can manage other users
        return $this->perimeterService->canManageUsers($user);
    }
}