<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PerimeterScope implements Scope
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
        $operationalTables = ['contacts', 'activities', 'tour_plans', 'sample_movements', 'objectifs', 'accounts', 'kol_profiles'];

        // 1. Holding / Global admin bypasses all filters
        if ($user->can('scope:global') || $user->hasRole('admin')) {
            return;
        }

        // 2. Récupérer les scopes (périmètres) directs de l'utilisateur
        $userScopeIds = DB::table('user_scopes')
            ->where('user_id', $user->id)
            ->pluck('organizational_unit_id')
            ->toArray();

        // Si l'utilisateur n'a aucun périmètre spécifique configuré, il accède à ses propres enregistrements (user_id = user->id)
        if (empty($userScopeIds)) {
            if ($table === 'users') {
                $builder->where('id', $user->id);
            } elseif (in_array($table, $operationalTables)) {
                $builder->where($table . '.user_id', $user->id);
            } elseif (in_array($table, ['products', 'categories', 'product_types', 'specialities', 'galenic_forms'])) {
                // Les catalogues référentiels sont accessibles à tous les utilisateurs authentifiés
                return;
            } else {
                $builder->whereRaw('1 = 0');
            }
            return;
        }

        // 3. Récupérer l'arbre de tous les descendants
        $allAccessibleUnitIds = $this->getDescendantUnitIds($userScopeIds);

        // 4. Filtrer la table `organizational_units` elle-même
        if ($table === 'organizational_units') {
            $builder->whereIn('id', $allAccessibleUnitIds);
            return;
        }

        // 5. Filtrer les tables opérationnelles
        $operationalTables = ['contacts', 'activities', 'tour_plans', 'sample_movements', 'objectifs', 'accounts', 'kol_profiles'];
        
        if (in_array($table, $operationalTables) || $table === 'users') {
            $columnToFilter = $table === 'users' ? 'id' : 'user_id';
            
            $builder->where(function ($sub) use ($table, $columnToFilter, $allAccessibleUnitIds, $user) {
                $sub->whereIn($table . '.' . $columnToFilter, function ($query) use ($allAccessibleUnitIds) {
                    $query->select('user_id')
                          ->from('user_scopes')
                          ->whereIn('organizational_unit_id', $allAccessibleUnitIds);
                });
                if ($columnToFilter === 'user_id') {
                    $sub->orWhere($table . '.user_id', $user->id);
                }
            });
            return;
        }

        // 6. Pour les produits, restreints par type de filiale ou pays (logique métier spécifique)
        if ($table === 'products') {
            // Logique simplifiée pour l'exemple : on pourrait chercher si l'utilisateur a accès au parent "filiale"
            // Pour l'instant, laissons passer ou implémentons une logique spécifique.
            return;
        }
    }

    /**
     * Remonte la hiérarchie pour trouver tous les enfants de manière récursive (sans CTE pour compatibilité).
     */
    protected function getDescendantUnitIds(array $parentIds): array
    {
        if (empty($parentIds)) {
            return [];
        }

        $allIds = $parentIds;
        $currentParentIds = $parentIds;

        // Boucle pour descendre l'arbre niveau par niveau
        while (true) {
            $childrenIds = DB::table('organizational_units')
                ->whereIn('parent_id', $currentParentIds)
                ->pluck('id')
                ->toArray();

            if (empty($childrenIds)) {
                break;
            }

            $allIds = array_merge($allIds, $childrenIds);
            $currentParentIds = $childrenIds; // Prochaine itération avec ces enfants
        }

        return array_unique($allIds);
    }
}
