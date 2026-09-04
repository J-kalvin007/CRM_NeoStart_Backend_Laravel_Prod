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
        if ($user->can('scope:global')) {
            return;
        }

        // 2. Déterminer le niveau de restriction de l'utilisateur (du plus précis au plus large)
        if ($user->team_id) {
            $this->applyHierarchyFilter($builder, $table, 'team_id', $user->team_id);
        } elseif ($user->sector_id) {
            $this->applyHierarchyFilter($builder, $table, 'sector_id', $user->sector_id);
        } elseif ($user->zone_id) {
            $this->applyHierarchyFilter($builder, $table, 'zone_id', $user->zone_id);
        } elseif ($user->territory_id) {
            $this->applyHierarchyFilter($builder, $table, 'territory_id', $user->territory_id);
        } elseif ($user->region_id) {
            $this->applyHierarchyFilter($builder, $table, 'region_id', $user->region_id);
        } elseif ($user->subsidiary_id) {
            $this->applyHierarchyFilter($builder, $table, 'subsidiary_id', $user->subsidiary_id);
        } elseif ($user->country_id) {
            $this->applyHierarchyFilter($builder, $table, 'country_id', $user->country_id);
        }
    }

    /**
     * Filtre dynamique basé sur la hiérarchie organisationnelle.
     */
    protected function applyHierarchyFilter(Builder $builder, string $table, string $levelColumn, string $levelId): void
    {
        // Tables structurelles simples
        if ($table === 'countries' && $levelColumn === 'country_id') {
            $builder->where('id', $levelId);
            return;
        }
        if ($table === 'subsidiaries' && in_array($levelColumn, ['country_id', 'subsidiary_id'])) {
            $column = $levelColumn === 'subsidiary_id' ? 'id' : $levelColumn;
            $builder->where($column, $levelId);
            return;
        }

        // Pour les tables opérationnelles (liées à un user_id)
        $operationalTables = ['contacts', 'activities', 'tour_plans', 'sample_movements', 'objectifs', 'accounts', 'kol_profiles'];
        if (in_array($table, $operationalTables) || $table === 'users') {
            $columnToFilter = $table === 'users' ? 'id' : 'user_id';
            
            // On récupère tous les users qui tombent sous ce niveau hiérarchique
            $builder->whereIn($table . '.' . $columnToFilter, function ($query) use ($levelColumn, $levelId) {
                $query->select('id')->from('users');
                
                // Si l'utilisateur est restreint par un niveau supérieur, il voit les users en dessous
                if ($levelColumn === 'team_id') {
                    $query->where('team_id', $levelId);
                } elseif ($levelColumn === 'sector_id') {
                    $query->where('sector_id', $levelId)->orWhereIn('team_id', function ($q) use ($levelId) {
                        $q->select('id')->from('teams')->where('sector_id', $levelId);
                    });
                } else {
                    // Pour simplifier et optimiser, si l'architecture de données de l'utilisateur est stricte, 
                    // il suffit de filtrer directement sur la colonne correspondante dans users.
                    $query->where($levelColumn, $levelId);
                }
            });
            return;
        }

        // Pour les produits, restreints à la filiale
        if ($table === 'products') {
            // On trouve la filiale correspondante au niveau actuel
            $subsidiaryId = $this->getSubsidiaryIdFromLevel($levelColumn, $levelId);
            if ($subsidiaryId) {
                $builder->where($table . '.subsidiary_id', $subsidiaryId);
            }
            return;
        }
    }

    /**
     * Remonte la hiérarchie pour trouver l'ID de la filiale.
     */
    protected function getSubsidiaryIdFromLevel(string $levelColumn, string $levelId): ?string
    {
        if ($levelColumn === 'subsidiary_id') return $levelId;
        if ($levelColumn === 'country_id') return null; // Les produits appartiennent à une filiale

        // Dans une implémentation complète, on ferait des jointures depuis le niveau bas vers subsidiary.
        // Pour des raisons de performance, l'ID de subsidiary est souvent dénormalisé sur les entités métier,
        // ou on peut le requêter une seule fois et le cacher.
        return DB::table('users')->where($levelColumn, $levelId)->value('subsidiary_id');
    }
}
