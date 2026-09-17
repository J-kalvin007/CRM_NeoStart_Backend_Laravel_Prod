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

        // 1. Holding / Global admin bypasses all filters
        if ($user->can('scope:global') || $user->hasRole('admin')) {
            return;
        }

        // 2. Récupérer les scopes (périmètres) directs de l'utilisateur
        $userScopeIds = DB::table('user_scopes')
            ->where('user_id', $user->id)
            ->pluck('organizational_unit_id')
            ->toArray();

        // Si l'utilisateur n'a aucun périmètre, on bloque tout (sauf si c'est lui-même)
        if (empty($userScopeIds)) {
            if ($table === 'users') {
                $builder->where('id', $user->id);
            } else {
                $builder->whereRaw('1 = 0');
            }
            return;
        }

        // 3. Récupérer l'arbre de tous les descendants (enfants, petits-enfants, etc.)
        $allAccessibleUnitIds = $this->getDescendantUnitIds($userScopeIds);

        // 4. Filtrer la table `organizational_units` elle-même
        if ($table === 'organizational_units') {
            $builder->whereIn('id', $allAccessibleUnitIds);
            return;
        }

        // 5. Filtrer les tables opérationnelles (liées à des utilisateurs)
        $operationalTables = ['contacts', 'activities', 'tour_plans', 'sample_movements', 'objectifs', 'accounts', 'kol_profiles'];
        
        if (in_array($table, $operationalTables) || $table === 'users') {
            $columnToFilter = $table === 'users' ? 'id' : 'user_id';
            
            // On récupère tous les utilisateurs qui ont au moins un scope dans notre arbre accessible
            $builder->whereIn($table . '.' . $columnToFilter, function ($query) use ($allAccessibleUnitIds) {
                $query->select('user_id')
                      ->from('user_scopes')
                      ->whereIn('organizational_unit_id', $allAccessibleUnitIds);
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
