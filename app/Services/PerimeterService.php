<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserScope;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\RoleHierarchyPermission;
use App\Models\Scopes\DynamicPerimeterScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class PerimeterService
{
    protected HierarchyConfigService $hierarchyService;
    
    const CACHE_PREFIX = 'user_perimeter';
    const CACHE_TTL = 1800; // 30 minutes

    public function __construct(HierarchyConfigService $hierarchyService)
    {
        $this->hierarchyService = $hierarchyService;
    }

    /**
     * Get all organizational units accessible by a user
     */
    public function getUserAccessibleUnits(User $user): Collection
    {
        // Admin and superadmin have access to everything
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return OrganizationalUnit::active()->with(['levelDefinition', 'parent'])->get();
        }

        return Cache::remember(
            self::CACHE_PREFIX . ':accessible_units:' . $user->id,
            self::CACHE_TTL,
            function () use ($user) {
                return DynamicPerimeterScope::getAccessibleUnits($user);
            }
        );
    }

    /**
     * Get user's direct organizational scopes
     */
    public function getUserScopes(User $user, bool $includeExpired = false): Collection
    {
        return Cache::remember(
            self::CACHE_PREFIX . ':scopes:' . $user->id . ':' . ($includeExpired ? '1' : '0'),
            self::CACHE_TTL,
            function () use ($user, $includeExpired) {
                $query = UserScope::with(['organizationalUnit.levelDefinition', 'organizationalUnit.parent'])
                    ->where('user_id', $user->id)
                    ->where('is_active', true);

                if (!$includeExpired) {
                    $query->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
                }
                
                return $query->get();
            }
        );
    }

    /**
     * Assign a user to an organizational unit perimeter
     */
    public function assignUserToPerimeter(User $user, int $organizationalUnitId, array $options = []): UserScope
    {
        $organizationalUnit = OrganizationalUnit::findOrFail($organizationalUnitId);
        
        // Check if user's role can access this level
        if (!$this->canUserAccessLevel($user, $organizationalUnit->level_code)) {
            throw ValidationException::withMessages([
                'organizational_unit_id' => 'User role cannot access this hierarchy level'
            ]);
        }

        // Check if assignment already exists and is active
        $existingScope = UserScope::where('user_id', $user->id)
            ->where('organizational_unit_id', $organizationalUnitId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($existingScope) {
            throw ValidationException::withMessages([
                'organizational_unit_id' => 'User already has access to this organizational unit'
            ]);
        }

        DB::beginTransaction();
        try {
            $userScope = UserScope::create([
                'user_id' => $user->id,
                'organizational_unit_id' => $organizationalUnitId,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
                'expires_at' => isset($options['expires_at']) ? Carbon::parse($options['expires_at']) : null,
                'is_active' => true,
                'access_type' => $options['access_type'] ?? 'full',
                'notes' => $options['notes'] ?? null,
            ]);

            // Clear user's cache
            $this->clearUserCache($user);
            
            DB::commit();
            
            return $userScope->load(['organizationalUnit.levelDefinition', 'assignedBy']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Revoke user access to an organizational unit
     */
    public function revokeUserPerimeter(User $user, int $organizationalUnitId, ?string $reason = null): bool
    {
        $userScope = UserScope::where('user_id', $user->id)
            ->where('organizational_unit_id', $organizationalUnitId)
            ->where('is_active', true)
            ->first();

        if (!$userScope) {
            return false;
        }

        DB::beginTransaction();
        try {
            $userScope->update([
                'is_active' => false,
                'revoked_by' => auth()->id(),
                'revoked_at' => now(),
                'revocation_reason' => $reason,
            ]);

            // Clear user's cache
            $this->clearUserCache($user);
            
            DB::commit();
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update user scope settings
     */
    public function updateUserScope(User $user, int $organizationalUnitId, array $updates): UserScope
    {
        $userScope = UserScope::where('user_id', $user->id)
            ->where('organizational_unit_id', $organizationalUnitId)
            ->where('is_active', true)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $userScope->update(array_intersect_key($updates, [
                'expires_at' => true,
                'access_type' => true,
                'notes' => true,
            ]));

            // Clear user's cache
            $this->clearUserCache($user);
            
            DB::commit();
            
            return $userScope->fresh(['organizationalUnit.levelDefinition']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get users within a specific organizational perimeter
     */
    public function getUsersInPerimeter(int $organizationalUnitId, bool $includeDescendants = true): Collection
    {
        $cacheKey = self::CACHE_PREFIX . ':users_in_perimeter:' . $organizationalUnitId . ':' . ($includeDescendants ? '1' : '0');
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($organizationalUnitId, $includeDescendants) {
            $unitIds = [$organizationalUnitId];
            
            if ($includeDescendants) {
                $organizationalUnit = OrganizationalUnit::findOrFail($organizationalUnitId);
                $descendants = $organizationalUnit->getDescendants();
                $unitIds = array_merge($unitIds, $descendants->pluck('id')->toArray());
            }

            return User::whereIn('id', function ($query) use ($unitIds) {
                $query->select('user_id')
                      ->from('user_scopes')
                      ->whereIn('organizational_unit_id', $unitIds)
                      ->where('is_active', true)
                      ->where(function ($q) {
                          $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                      });
            })
            ->with(['roles', 'team'])
            ->get();
        });
    }

    /**
     * Check if a user can access a specific organizational unit
     */
    public function canUserAccessUnit(User $user, int $organizationalUnitId): bool
    {
        // Admin and superadmin can access everything
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return true;
        }

        return DynamicPerimeterScope::userCanAccessUnit($user, $organizationalUnitId);
    }

    /**
     * Check if user's role can access a specific hierarchy level
     */
    public function canUserAccessLevel(User $user, string $levelCode): bool
    {
        // Admin and superadmin can access everything
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return true;
        }

        $roles = $user->roles->pluck('name');
        
        foreach ($roles as $roleName) {
            if ($this->hierarchyService->roleCanAccessLevel($roleName, $levelCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get hierarchy levels accessible by user's roles
     */
    public function getUserAccessibleLevels(User $user): Collection
    {
        // Admin and superadmin can access all levels
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return $this->hierarchyService->getHierarchyLevels();
        }

        return Cache::remember(
            self::CACHE_PREFIX . ':accessible_levels:' . $user->id,
            self::CACHE_TTL,
            function () use ($user) {
                $roles = $user->roles->pluck('name');
                $accessibleLevels = new Collection();

                foreach ($roles as $roleName) {
                    $roleLevels = $this->hierarchyService->getAccessibleLevelsForRole($roleName);
                    $accessibleLevels = $accessibleLevels->merge($roleLevels);
                }

                return $accessibleLevels->unique('id')->sortBy('level_order');
            }
        );
    }

    /**
     * Get organizational units where user can create new units
     */
    public function getUnitsWhereUserCanCreate(User $user): Collection
    {
        // Admin and superadmin can create anywhere
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return OrganizationalUnit::active()->with('levelDefinition')->get();
        }

        return Cache::remember(
            self::CACHE_PREFIX . ':can_create_units:' . $user->id,
            self::CACHE_TTL,
            function () use ($user) {
                $accessibleUnits = $this->getUserAccessibleUnits($user);
                $canCreateUnits = new Collection();

                foreach ($accessibleUnits as $unit) {
                    // Check if user's roles allow creation at the next level
                    $nextLevelOrder = $unit->level_order + 1;
                    $nextLevel = $this->hierarchyService->getLevelByOrder($nextLevelOrder);
                    
                    if ($nextLevel && $this->userCanCreateAtLevel($user, $nextLevel->level_code)) {
                        $canCreateUnits->push($unit);
                    }
                }

                return $canCreateUnits;
            }
        );
    }

    /**
     * Check if user can create units at a specific level
     */
    public function userCanCreateAtLevel(User $user, string $levelCode): bool
    {
        $roles = $user->roles;
        
        foreach ($roles as $role) {
            $permissions = RoleHierarchyPermission::where('role_name', $role->name)
                ->where('can_create', true)
                ->get();

            foreach ($permissions as $permission) {
                if ($permission->hierarchy_level_definition_id) {
                    $level = $permission->hierarchyLevel;
                    if ($level && $level->level_code === $levelCode) {
                        return true;
                    }
                } elseif ($permission->min_level_order && $permission->max_level_order) {
                    $level = $this->hierarchyService->getLevelByCode($levelCode);
                    if ($level && 
                        $level->level_order >= $permission->min_level_order && 
                        $level->level_order <= $permission->max_level_order) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Bulk assign users to an organizational unit
     */
    public function bulkAssignUsersToPerimeter(array $userIds, int $organizationalUnitId, array $options = []): array
    {
        $results = ['success' => [], 'failed' => []];
        
        DB::beginTransaction();
        try {
            foreach ($userIds as $userId) {
                try {
                    $user = User::findOrFail($userId);
                    $userScope = $this->assignUserToPerimeter($user, $organizationalUnitId, $options);
                    $results['success'][] = [
                        'user_id' => $userId,
                        'scope_id' => $userScope->id
                    ];
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    /**
     * Get perimeter assignment history for a user
     */
    public function getUserPerimeterHistory(User $user): Collection
    {
        return UserScope::with(['organizationalUnit.levelDefinition', 'assignedBy', 'revokedBy'])
            ->where('user_id', $user->id)
            ->orderByDesc('assigned_at')
            ->get();
    }

    /**
     * Get users with expiring access
     */
    public function getUsersWithExpiringAccess(int $daysAhead = 7): Collection
    {
        $expirationDate = now()->addDays($daysAhead);
        
        return User::whereIn('id', function ($query) use ($expirationDate) {
            $query->select('user_id')
                  ->from('user_scopes')
                  ->where('is_active', true)
                  ->whereBetween('expires_at', [now(), $expirationDate]);
        })
        ->with(['userScopes' => function ($query) use ($expirationDate) {
            $query->where('is_active', true)
                  ->whereBetween('expires_at', [now(), $expirationDate])
                  ->with('organizationalUnit');
        }])
        ->get();
    }

    /**
     * Extend user scope expiration
     */
    public function extendUserScopeExpiration(User $user, int $organizationalUnitId, Carbon $newExpirationDate): UserScope
    {
        $userScope = UserScope::where('user_id', $user->id)
            ->where('organizational_unit_id', $organizationalUnitId)
            ->where('is_active', true)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $userScope->update([
                'expires_at' => $newExpirationDate,
                'extended_by' => auth()->id(),
                'extended_at' => now(),
            ]);

            // Clear user's cache
            $this->clearUserCache($user);
            
            DB::commit();
            
            return $userScope->fresh();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get perimeter statistics for reporting
     */
    public function getPerimeterStatistics(): array
    {
        return Cache::remember(
            self::CACHE_PREFIX . ':statistics',
            self::CACHE_TTL,
            function () {
                $stats = [];

                // Total active user scopes
                $stats['total_active_scopes'] = UserScope::where('is_active', true)
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    })
                    ->count();

                // Scopes by level
                $stats['scopes_by_level'] = UserScope::join('organizational_units', 'user_scopes.organizational_unit_id', '=', 'organizational_units.id')
                    ->join('hierarchy_level_definitions', 'organizational_units.level_definition_id', '=', 'hierarchy_level_definitions.id')
                    ->where('user_scopes.is_active', true)
                    ->where(function ($query) {
                        $query->whereNull('user_scopes.expires_at')
                              ->orWhere('user_scopes.expires_at', '>', now());
                    })
                    ->selectRaw('hierarchy_level_definitions.level_name, COUNT(*) as count')
                    ->groupBy('hierarchy_level_definitions.level_name')
                    ->get()
                    ->pluck('count', 'level_name')
                    ->toArray();

                // Users without scopes
                $stats['users_without_scopes'] = User::whereNotIn('id', function ($query) {
                    $query->select('user_id')
                          ->from('user_scopes')
                          ->where('is_active', true)
                          ->where(function ($q) {
                              $q->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                          });
                })
                ->where('is_active', true)
                ->count();

                // Expiring scopes in next 30 days
                $stats['expiring_scopes_30_days'] = UserScope::where('is_active', true)
                    ->whereBetween('expires_at', [now(), now()->addDays(30)])
                    ->count();

                return $stats;
            }
        );
    }

    /**
     * Validate user perimeter assignments
     */
    public function validateUserPerimeters(User $user): array
    {
        $issues = [];
        $userScopes = $this->getUserScopes($user);

        foreach ($userScopes as $scope) {
            // Check if user's role can still access this level
            if (!$this->canUserAccessLevel($user, $scope->organizationalUnit->level_code)) {
                $issues[] = [
                    'type' => 'invalid_role_access',
                    'scope_id' => $scope->id,
                    'organizational_unit' => $scope->organizationalUnit->name,
                    'level_code' => $scope->organizationalUnit->level_code,
                    'message' => 'User role no longer has access to this hierarchy level'
                ];
            }

            // Check if organizational unit is still active
            if (!$scope->organizationalUnit->is_active) {
                $issues[] = [
                    'type' => 'inactive_unit',
                    'scope_id' => $scope->id,
                    'organizational_unit' => $scope->organizationalUnit->name,
                    'message' => 'Organizational unit is no longer active'
                ];
            }
        }

        return $issues;
    }

    /**
     * Clear all cache related to a specific user
     */
    public function clearUserCache(User $user): void
    {
        $keys = [
            self::CACHE_PREFIX . ':accessible_units:' . $user->id,
            self::CACHE_PREFIX . ':scopes:' . $user->id,
            self::CACHE_PREFIX . ':accessible_levels:' . $user->id,
            self::CACHE_PREFIX . ':can_create_units:' . $user->id,
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Clear all perimeter cache
     */
    public function clearAllCache(): void
    {
        // Clear statistics cache
        Cache::forget(self::CACHE_PREFIX . ':statistics');
        
        // Clear pattern-based cache (if using Redis)
        if (method_exists(Cache::getStore(), 'flush')) {
            $cacheKeys = Cache::getRedis()->keys(self::CACHE_PREFIX . ':*');
            if ($cacheKeys) {
                Cache::getRedis()->del($cacheKeys);
            }
        }
    }

    /**
     * Alias for canUserAccessUnit
     */
    public function canAccessUnit(User $user, int|string $organizationalUnitId): bool
    {
        return $this->canUserAccessUnit($user, (int)$organizationalUnitId);
    }

    /**
     * Check if user can create units in their perimeter
     */
    public function canCreateUnits(User $user): bool
    {
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return true;
        }

        // Check if there are any units where user can create
        return $this->getUnitsWhereUserCanCreate($user)->isNotEmpty();
    }

    /**
     * Check if user can edit units in their perimeter
     */
    public function canEditUnits(User $user): bool
    {
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return true;
        }

        // For simplicity, using same logic as create. Can be refined.
        return $this->canCreateUnits($user);
    }

    /**
     * Check if user can delete units in their perimeter
     */
    public function canDeleteUnits(User $user): bool
    {
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return true;
        }

        // For simplicity, using same logic as create. Can be refined.
        return $this->canCreateUnits($user);
    }

    /**
     * Get hierarchy path for a unit
     */
    public function getHierarchyPath(string $id): \Illuminate\Support\Collection
    {
        $unit = OrganizationalUnit::findOrFail($id);
        $path = collect();
        $current = $unit;

        while ($current) {
            $path->prepend([
                'id' => $current->id,
                'name' => $current->name,
                'level_code' => $current->level_code,
            ]);
            $current = $current->parent;
        }

        return $path;
    }

    public function canManageUsers(User $user): bool
    {
        return $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin', 'DIR_COMMERCIALE']);
    }

    public function getPrimaryScope(User $user): ?UserScope
    {
        return UserScope::where('user_id', $user->id)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->first();
    }

    public function validateScopeAssignment(User $currentUser, User $user, string $unitId): array
    {
        if (!$this->canAccessUnit($currentUser, $unitId)) {
            return ['allowed' => false, 'errors' => ['unit_id' => 'Cannot assign unit outside of your perimeter']];
        }
        return ['allowed' => true, 'errors' => []];
    }

    public function setPrimaryScope(User $user, string $unitId): bool
    {
        DB::transaction(function() use ($user, $unitId) {
            UserScope::where('user_id', $user->id)->update(['is_primary' => false]);
            UserScope::where('user_id', $user->id)->where('organizational_unit_id', $unitId)->update(['is_primary' => true]);
        });
        return true;
    }

    public function getAccessibleUsers(User $user): Collection
    {
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return User::with(['roles'])->get();
        }

        $units = $this->getUserAccessibleUnits($user);
        $userIds = UserScope::whereIn('organizational_unit_id', $units->pluck('id'))
            ->where('is_active', true)
            ->pluck('user_id');

        return User::whereIn('id', $userIds)->with(['roles'])->get();
    }

    public function getManageableUnits(User $user): Collection
    {
        return $this->getUserAccessibleUnits($user);
    }
}