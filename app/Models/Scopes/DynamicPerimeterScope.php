<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

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
        // 1. Global admin & superadmin bypass isolation, BUT can filter by X-Country-Id
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin', 'super-admin'])) {
            return;
        }

        // 2. Get user's country
        $countryId = $user->country_id;

        // 3. Apply filtering
        $this->applyHierarchyFiltering($builder, $table, $user, $countryId);
    }

    /**
     * Collaborateurs encadrés (récursif via manager_id) d'un utilisateur de la hiérarchie.
     */
    public static function teamIdsFor(User $user): array
    {
        if (!$user->hasAnyRole(['superviseur', 'directeur_regional', 'directeur_commercial', 'directeur-commercial', 'directeur_marketing', 'directeur-marketing', 'sales_manager'])) {
            return [];
        }

        // Une requête suivante doit voir les nouveaux collaborateurs et les réaffectations.
        $request = request();
        $cache = $request->attributes->get('crm.team_ids', []);
        $cache[$user->id] ??= $user->teamMemberIds();
        $request->attributes->set('crm.team_ids', $cache);
        return $cache[$user->id];
    }

    /**
     * Apply hierarchy-based filtering using country_id
     */
    protected function applyHierarchyFiltering(Builder $builder, string $table, User $user, ?string $countryId, bool $isAdmin = false): void
    {
        // Reference catalogs are accessible to all
        $catalogTables = [
            'categories', 'product_types', 'specialities', 'galenic_forms',
            'countries'
        ];

        if (in_array($table, $catalogTables)) {
            return; // No filtering for catalogs
        }
        
        // Special handling for different table types
        switch ($table) {
            case 'users':
                $this->filterUsers($builder, $user, $countryId, $isAdmin);
                break;
                
            case 'documents':
                // We keep documents accessible to all in the same country for now
                // Alternatively, we could filter them by role/speciality
                $builder->whereRaw('1 = 1');
                break;
                
            default:
                $this->filterOperationalTables($builder, $table, $user, $countryId, $isAdmin);
                break;
        }
    }

    /**
     * Filter users table with role-based logic
     */
    protected function filterUsers(Builder $builder, User $user, ?string $countryId, bool $isAdmin = false): void
    {
        if ($isAdmin && $countryId) {
            $builder->where('users.country_id', $countryId);
            return;
        }
        // Supervisors and managers can see their team members
        if ($user->hasAnyRole(['superviseur', 'directeur_regional', 'directeur_commercial', 'directeur-commercial', 'directeur_marketing', 'directeur-marketing', 'sales_manager'])) {
            $builder->whereIn('users.id', array_merge([$user->id], self::teamIdsFor($user)));
            return;
        }

        // For other roles, filter by country if available
        $builder->where(function ($query) use ($user, $countryId) {
            $query->where('id', $user->id); // Always include self
            
            if ($countryId) {
                $query->orWhere('country_id', $countryId);
            }
        });
    }

    /**
     * Filter operational tables (contacts, activities, etc.)
     */
    protected function filterOperationalTables(Builder $builder, string $table, User $user, ?string $countryId, bool $isAdmin = false): void
    {
        $operationalTables = [
            'contacts', 'activities', 'tour_plans', 'sample_movements', 'objectifs', 
            'accounts', 'kol_profiles', 'opportunities', 'orders', 'sales',
            'expenses', 'events', 'tasks', 'indicators'
        ];

        if ($table === 'products') {
            // No strict restriction for products anymore, or we could add country_id to products
            return;
        }

        if (in_array($table, $operationalTables)) {
            if ($isAdmin && $countryId) {
                // Admin strictly filters by the given country
                $builder->whereHas('user', function($q) use ($countryId) {
                    $q->where('country_id', $countryId);
                });
                return;
            }

            // Filter by users who are in the same country
            $builder->where(function ($query) use ($table, $user, $countryId) {
                $query->where($table . '.user_id', $user->id); // Always include own records

                // Supervisors and managers can see their team members' records
                if ($user->hasAnyRole(['superviseur', 'directeur_regional', 'directeur_commercial', 'directeur-commercial', 'directeur_marketing', 'directeur-marketing', 'sales_manager'])) {
                    $teamIds = self::teamIdsFor($user);
                    if (!empty($teamIds)) {
                        $query->orWhereIn($table . '.user_id', $teamIds);
                    }
                }

                // If user is tied to a country, they can potentially see all country data depending on the old rule.
                // The old rule said: if you have a scope, you see everyone in that scope.
                // We replicate it: if you have a country_id, you see everyone's data in the country.
                if ($countryId) {
                    $query->orWhereHas('user', function($q) use ($countryId) {
                        $q->where('country_id', $countryId);
                    });
                    
                    // For accounts: include those without a user_id but maybe in the same country?
                    // But accounts have user_id, so the above rule covers it.
                }
            });
        } else {
            // For unknown tables, restrict access to be safe
            $builder->whereRaw('1 = 0');
        }
    }

    /**
     * Check if user has access to a specific unit (deprecated, but keep interface for now)
     */
    public static function userCanAccessUnit(User $user, string $unitId): bool
    {
        return true;
    }

    /**
     * Get all organizational units accessible by the user (deprecated)
     */
    public static function getAccessibleUnits(User $user)
    {
        return collect();
    }
}
