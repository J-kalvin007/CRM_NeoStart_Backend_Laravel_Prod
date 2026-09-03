<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

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

        // 1. Si l'utilisateur a la permission globale, il voit tout (bypass du scope)
        if ($user->can('scope:global')) {
            return;
        }

        // 2. S'il a la permission de voir tout son pays
        if ($user->can('scope:country') && $user->country_id) {
            $this->applyCountryFilter($builder, $table, $user->country_id);
            return;
        }

        // 3. S'il a la permission de voir toute sa filiale
        if ($user->can('scope:subsidiary') && $user->subsidiary_id) {
            $this->applySubsidiaryFilter($builder, $table, $user->subsidiary_id);
            return;
        }

        // 4. Sinon (par défaut ou avec permission stricte équipe), on restreint au plus bas niveau possible
        if ($user->team_id) {
            $this->applyTeamFilter($builder, $table, $user->team_id);
        } elseif ($user->subsidiary_id) {
            $this->applySubsidiaryFilter($builder, $table, $user->subsidiary_id);
        } elseif ($user->country_id) {
            $this->applyCountryFilter($builder, $table, $user->country_id);
        }
    }

    /**
     * Filtre les données pour n'afficher que ce qui appartient à une filiale spécifique.
     */
    protected function applySubsidiaryFilter(Builder $builder, string $table, string $subsidiaryId): void
    {
        switch ($table) {
            case 'subsidiaries':
                $builder->where($table . '.id', $subsidiaryId);
                break;
            case 'regions':
                $builder->where($table . '.subsidiary_id', $subsidiaryId);
                break;
            case 'territories':
                $builder->whereHas('region', function ($query) use ($subsidiaryId) {
                    $query->where('subsidiary_id', $subsidiaryId);
                });
                break;
            case 'teams':
                $builder->whereHas('territory.region', function ($query) use ($subsidiaryId) {
                    $query->where('subsidiary_id', $subsidiaryId);
                });
                break;
            case 'users':
                $builder->where($table . '.subsidiary_id', $subsidiaryId);
                break;
            // TODO : Ajouter les futures tables du SFA (Visites, Médecins, Pharmacies)
            // case 'doctors':
            // case 'pharmacies':
            // case 'visits':
            //     $builder->where($table . '.subsidiary_id', $subsidiaryId);
            //     break;
        }
    }

    /**
     * Filtre les données pour n'afficher que ce qui appartient à un pays spécifique.
     */
    protected function applyCountryFilter(Builder $builder, string $table, string $countryId): void
    {
        switch ($table) {
            case 'countries':
                $builder->where($table . '.id', $countryId);
                break;
            case 'subsidiaries':
                $builder->where($table . '.country_id', $countryId);
                break;
            case 'users':
                $builder->where($table . '.country_id', $countryId);
                break;
            case 'regions':
                $builder->whereHas('subsidiary', function ($query) use ($countryId) {
                    $query->where('country_id', $countryId);
                });
                break;
            case 'territories':
                $builder->whereHas('region.subsidiary', function ($query) use ($countryId) {
                    $query->where('country_id', $countryId);
                });
                break;
            case 'teams':
                $builder->whereHas('territory.region.subsidiary', function ($query) use ($countryId) {
                    $query->where('country_id', $countryId);
                });
                break;
        }
    }

    /**
     * Restriction supplémentaire pour restreindre à une équipe précise.
     */
    protected function applyTeamFilter(Builder $builder, string $table, string $teamId): void
    {
        switch ($table) {
            case 'teams':
                $builder->where($table . '.id', $teamId);
                break;
            case 'users':
                $builder->where($table . '.team_id', $teamId);
                break;
            // Pour les futures tables :
            // case 'visits':
            //     $builder->where($table . '.team_id', $teamId);
            //     break;
        }
    }
}
