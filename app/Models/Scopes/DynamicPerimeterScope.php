<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\OrganizationalUnit;
use App\Models\UserScope;

class DynamicPerimeterScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (!Auth::hasUser()) {
            return;
        }

        $user = Auth::user();
        $table = $model->getTable();
        
        // 1. Global admin & superadmin bypass isolation
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return;
        }

        // 2. Get user's organizational scopes
        $userScopes = $this->getUserScopes($user);
        
        // 3. If no scopes configured, apply default restrictions
        if ($userScopes->isEmpty()) {
            $this->applyDefaultRestrictions($builder, $table, $user);
            return;
        }

        // 4. Apply dynamic hierarchy-based filtering
        $this->applyHierarchyFiltering($builder, $table, $user, $userScopes);
    }

    /** @var array<string, array> Équipe encadrée, mise en cache le temps de la requête */
    protected static array $teamIdsCache = [];

    /**
     * Collaborateurs encadrés (récursif via manager_id) d'un utilisateur de la hiérarchie.
     */
    public static function teamIdsFor(User $user): array
    {
        if (!$user->hasAnyRole(['superviseur', 'directeur_regional', 'directeur_commercial', 'directeur-commercial', 'directeur_marketing', 'directeur-marketing', 'sales_manager'])) {
            return [];
        }

        return self::$teamIdsCache[$user->id] ??= $user->teamMemberIds();
    }

    /**
     * Get user's active organizational scopes with hierarchy information
     */
    protected function getUserScopes(User $user)
    {
        return UserScope::with(['organizationalUnit.levelDefinition', 'organizationalUnit.organizationConfig'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->get();
    }

    /**
     * Apply default restrictions when user has no organizational scopes
     */
    protected function applyDefaultRestrictions(Builder $builder, string $table, User $user): void
    {
        $operationalTables = [
            'contacts', 'activities', 'tour_plans', 'sample_movements', 'objectifs', 
            'accounts', 'kol_profiles', 'opportunities', 'orders', 'sales',
            'expenses', 'events', 'tasks', 'indicators'
        ];

        // Sans périmètre configuré, la hiérarchie voit au moins les délégués qu'elle encadre directement
        $teamIds = self::teamIdsFor($user);

        if ($table === 'users') {
            $builder->whereIn('users.id', array_merge([$user->id], $teamIds));
        } elseif (in_array($table, $operationalTables)) {
            $builder->whereIn($table . '.user_id', array_merge([$user->id], $teamIds));
        } elseif (in_array($table, ['products', 'categories', 'product_types', 'specialities', 'galenic_forms'])) {
            // Reference catalogs are accessible to all authenticated users
            return;
        } elseif ($table === 'documents') {
            $this->filterDocuments($builder, $user, []);
        } else {
            // Restrict access to other tables
            $builder->whereRaw('1 = 0');
        }
    }

    /**
     * Apply hierarchy-based filtering using organizational units
     */
    protected function applyHierarchyFiltering(Builder $builder, string $table, User $user, $userScopes): void
    {
        // Get all accessible organizational unit IDs (user's units + all descendants)
        $accessibleUnitIds = $this->getAccessibleUnitIds($userScopes);
        
        // Special handling for different table types
        switch ($table) {
            case 'organizational_units':
                $this->filterOrganizationalUnits($builder, $accessibleUnitIds);
                break;
                
            case 'users':
                $this->filterUsers($builder, $user, $accessibleUnitIds);
                break;
                
            case 'documents':
                $this->filterDocuments($builder, $user, $accessibleUnitIds);
                break;
                
            default:
                $this->filterOperationalTables($builder, $table, $user, $accessibleUnitIds);
                break;
        }
    }

    /**
     * Get all accessible unit IDs (direct scopes + descendants)
     */
    protected function getAccessibleUnitIds($userScopes): array
    {
        $directUnitIds = $userScopes->pluck('organizational_unit_id')->toArray();
        
        if (empty($directUnitIds)) {
            return [];
        }

        return $this->getDescendantUnitIds($directUnitIds);
    }

    /**
     * Get all descendant unit IDs recursively
     */
    protected function getDescendantUnitIds(array $parentIds): array
    {
        if (empty($parentIds)) {
            return [];
        }

        $allIds = $parentIds;
        $currentParentIds = $parentIds;

        // Iterate level by level to get all descendants
        while (true) {
            $childrenIds = DB::table('organizational_units')
                ->whereIn('parent_id', $currentParentIds)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            if (empty($childrenIds)) {
                break;
            }

            $allIds = array_merge($allIds, $childrenIds);
            $currentParentIds = $childrenIds;
        }

        return array_unique($allIds);
    }

    /**
     * Filter organizational_units table
     */
    protected function filterOrganizationalUnits(Builder $builder, array $accessibleUnitIds): void
    {
        $builder->whereIn('id', $accessibleUnitIds);
    }

    /**
     * Filter users table with role-based logic
     */
    protected function filterUsers(Builder $builder, User $user, array $accessibleUnitIds): void
    {
        // Supervisors and managers can see their team members
        if ($user->hasAnyRole(['superviseur', 'directeur_regional', 'directeur_commercial', 'directeur-commercial', 'directeur_marketing', 'directeur-marketing', 'sales_manager'])) {
            $builder->whereIn('users.id', array_merge([$user->id], self::teamIdsFor($user)));
            return;
        }

        // For other roles, filter by organizational perimeter
        $builder->where(function ($query) use ($user, $accessibleUnitIds) {
            $query->where('id', $user->id); // Always include self
            
            if (!empty($accessibleUnitIds)) {
                $query->orWhereIn('id', function ($subQuery) use ($accessibleUnitIds) {
                    $subQuery->select('user_id')
                             ->from('user_scopes')
                             ->whereIn('organizational_unit_id', $accessibleUnitIds)
                             ->where('is_active', true);
                });
            }
        });
    }

    /**
     * Filter operational tables (contacts, activities, etc.)
     */
    protected function filterOperationalTables(Builder $builder, string $table, User $user, array $accessibleUnitIds): void
    {
        $operationalTables = [
            'contacts', 'activities', 'tour_plans', 'sample_movements', 'objectifs', 
            'accounts', 'kol_profiles', 'opportunities', 'orders', 'sales',
            'expenses', 'events', 'tasks', 'indicators'
        ];

        // Reference catalogs are accessible to all
        $catalogTables = [
            'categories', 'product_types', 'specialities', 'galenic_forms',
            'countries', 'regions', 'subsidiaries', 'territories'
        ];

        if (in_array($table, $catalogTables)) {
            return; // No filtering for catalogs
        }

        if ($table === 'products') {
            // Products are filtered by the perimeters they are linked to
            $builder->where(function ($query) use ($accessibleUnitIds) {
                if (!empty($accessibleUnitIds)) {
                    $query->whereExists(function ($subQuery) use ($accessibleUnitIds) {
                        $subQuery->select(DB::raw(1))
                                 ->from('organizational_unit_product')
                                 ->whereColumn('organizational_unit_product.product_id', 'products.id')
                                 ->whereIn('organizational_unit_product.organizational_unit_id', $accessibleUnitIds);
                    });
                } else {
                    $query->whereRaw('1 = 0');
                }
            });
            return;
        }

        if (in_array($table, $operationalTables)) {
            // Filter by users who have access to the organizational units
            $builder->where(function ($query) use ($table, $user, $accessibleUnitIds) {
                $query->where($table . '.user_id', $user->id); // Always include own records

                // For the accounts table: also include establishments linked to the delegate's contacts
                if ($table === 'accounts') {
                    $query->orWhereIn('accounts.id', function ($sub) use ($user) {
                        $sub->select('account_id')
                            ->from('contacts')
                            ->where('user_id', $user->id)
                            ->whereNotNull('account_id');
                    });
                }
                
                // Supervisors and managers can see their team members' records
                if ($user->hasAnyRole(['superviseur', 'directeur_regional', 'directeur_commercial', 'directeur-commercial', 'directeur_marketing', 'directeur-marketing', 'sales_manager'])) {
                    $teamIds = self::teamIdsFor($user);
                    if (!empty($teamIds)) {
                        $query->orWhereIn($table . '.user_id', $teamIds);
                    }

                    // For supervisors managing accounts: also include establishments linked to their team's contacts
                    if ($table === 'accounts') {
                        $query->orWhereIn('accounts.id', function ($sub) use ($user) {
                            $sub->select('account_id')
                                ->from('contacts')
                                ->whereIn('user_id', self::teamIdsFor($user))
                                ->whereNotNull('account_id');
                        });
                    }
                }

                if (!empty($accessibleUnitIds)) {
                    $query->orWhereIn($table . '.user_id', function ($subQuery) use ($accessibleUnitIds) {
                        $subQuery->select('user_id')
                                 ->from('user_scopes')
                                 ->whereIn('organizational_unit_id', $accessibleUnitIds)
                                 ->where('is_active', true);
                    });

                    // For accounts with unit scope: also include establishments linked to contacts of users in those units
                    if ($table === 'accounts') {
                        $query->orWhereIn('accounts.id', function ($sub) use ($accessibleUnitIds) {
                            $sub->select('account_id')
                                ->from('contacts')
                                ->whereIn('user_id', function ($s) use ($accessibleUnitIds) {
                                    $s->select('user_id')
                                      ->from('user_scopes')
                                      ->whereIn('organizational_unit_id', $accessibleUnitIds)
                                      ->where('is_active', true);
                                })
                                ->whereNotNull('account_id');
                        });
                    }
                }
            });
        } else {
            // For unknown tables, restrict access
            $builder->whereRaw('1 = 0');
        }
    }

    /**
     * Filter documents table based on organization, role, and speciality
     */
    protected function filterDocuments(Builder $builder, User $user, array $accessibleUnitIds): void
    {
        $builder->where(function ($query) use ($user, $accessibleUnitIds) {
            // 1. Check organizational unit (perimeter)
            $query->where(function ($q) use ($accessibleUnitIds) {
                $q->whereNull('organizational_unit_id');
                if (!empty($accessibleUnitIds)) {
                    $q->orWhereIn('organizational_unit_id', $accessibleUnitIds);
                }
            });

            // 2. Check role restriction
            $query->where(function ($q) use ($user) {
                $q->whereNull('role_id');
                // The user object might not have role_id directly if using spatie, but we can check hasRole or directly against user's roles
                // If using spatie, the user has multiple roles.
                // Assuming we have roles relation or role_id
                $userRoleIds = $user->roles()->pluck('id')->toArray();
                if (!empty($userRoleIds)) {
                    $q->orWhereIn('role_id', $userRoleIds);
                }
            });

            // 3. Check speciality restriction
            $query->where(function ($q) use ($user) {
                $q->whereNull('speciality_id');
                // Assuming user has a speciality_id or specialities
                if (!empty($user->speciality_id)) {
                    $q->orWhere('speciality_id', $user->speciality_id);
                }
            });
        });
    }

    /**
     * Check if user has access to a specific organizational unit
     */
    public static function userCanAccessUnit(User $user, string $unitId): bool
    {
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return true;
        }

        $userScopes = UserScope::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->pluck('organizational_unit_id')
            ->toArray();

        if (empty($userScopes)) {
            return false;
        }

        // Check if the unit is in user's direct scopes or descendants
        $scope = new self();
        $accessibleIds = $scope->getDescendantUnitIds($userScopes);
        
        return in_array($unitId, $accessibleIds);
    }

    /**
     * Get all organizational units accessible by the user
     */
    public static function getAccessibleUnits(User $user)
    {
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return OrganizationalUnit::active()->get();
        }

        $userScopes = UserScope::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->pluck('organizational_unit_id')
            ->toArray();

        if (empty($userScopes)) {
            return collect();
        }

        $scope = new self();
        $accessibleIds = $scope->getDescendantUnitIds($userScopes);
        
        return OrganizationalUnit::whereIn('id', $accessibleIds)
                                ->active()
                                ->with(['levelDefinition', 'parent'])
                                ->orderBy('level_order')
                                ->orderBy('display_order')
                                ->orderBy('name')
                                ->get();
    }
}