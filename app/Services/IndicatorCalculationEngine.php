<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\Objectif;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class IndicatorCalculationEngine
{
    /**
     * Déclenché par les Observers (ex: ActivityObserver, OpportunityObserver)
     * Recalcule les objectifs pertinents lorsqu'une donnée source change.
     */
    public function updateObjectivesForModel(Model $model): void
    {
        try {
            // 1. Trouver les indicateurs qui se basent sur ce modèle
            $modelClass = get_class($model);
            
            $indicators = Indicator::where('is_active', true)
                ->where('data_source_model', $modelClass)
                ->get();

            if ($indicators->isEmpty()) {
                return;
            }

            // 2. Déterminer l'utilisateur responsable de cette donnée
            $userId = $model->user_id ?? null;
            $dateSource = $this->getDateFromModel($model);

            if (!$userId || !$dateSource) {
                return;
            }

            // 3. Pour chaque indicateur, trouver les objectifs en cours qui couvrent cette date
            foreach ($indicators as $indicator) {
                $objectifs = Objectif::where('indicator_id', $indicator->id)
                    ->where('user_id', $userId)
                    ->where('statut', 'actif')
                    ->whereDate('date_debut', '<=', $dateSource)
                    ->whereDate('date_fin', '>=', $dateSource)
                    ->get();

                foreach ($objectifs as $objectif) {
                    $this->recalculateObjective($objectif, $indicator);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("IndicatorCalculationEngine error: " . $e->getMessage());
        }
    }

    /**
     * Recalcule complètement la valeur réalisée d'un objectif donné.
     */
    public function recalculateObjective(Objectif $objectif, Indicator $indicator): void
    {
        $modelClass = $indicator->data_source_model;
        if (!class_exists($modelClass)) {
            Log::warning("Modèle source introuvable pour l'indicateur {$indicator->code}");
            return;
        }

        // Construire la requête de base
        $query = $modelClass::where('user_id', $objectif->user_id);

        // Appliquer les filtres de date en fonction du type de modèle
        $dateColumn = $this->getDateColumnForModel($modelClass);
        if ($dateColumn) {
            $query->whereDate($dateColumn, '>=', $objectif->date_debut)
                  ->whereDate($dateColumn, '<=', $objectif->date_fin);
        }

        // Ajouter des filtres spécifiques (ex: ne compter que les activités 'realisee' ou opportunités 'won')
        $query = $this->applySpecificFilters($query, $modelClass);

        // Calculer la nouvelle valeur selon le type de calcul de l'indicateur
        $newValue = 0;
        
        switch ($indicator->calculation_type) {
            case 'count':
                $newValue = $query->count();
                break;
            case 'sum':
                if ($indicator->data_source_field) {
                    $newValue = $query->sum($indicator->data_source_field);
                }
                break;
            case 'average':
                if ($indicator->data_source_field) {
                    $newValue = $query->avg($indicator->data_source_field);
                }
                break;
            case 'max':
                if ($indicator->data_source_field) {
                    $newValue = $query->max($indicator->data_source_field);
                }
                break;
            case 'min':
                if ($indicator->data_source_field) {
                    $newValue = $query->min($indicator->data_source_field);
                }
                break;
            case 'manual':
            default:
                // Ne rien faire pour les calculs manuels
                return;
        }

        // Mettre à jour l'objectif
        $objectif->valeur_realisee = $newValue;
        $objectif->recalculerTaux();
        $objectif->save();
    }

    /**
     * Détermine la date pertinente de l'instance pour savoir à quelle période l'affecter.
     */
    private function getDateFromModel(Model $model): ?Carbon
    {
        $dateColumn = $this->getDateColumnForModel(get_class($model));
        if ($dateColumn && isset($model->$dateColumn)) {
            return Carbon::parse($model->$dateColumn);
        }
        
        return $model->created_at;
    }

    /**
     * Retourne la colonne de date pertinente selon le modèle.
     */
    private function getDateColumnForModel(string $modelClass): ?string
    {
        return match ($modelClass) {
            'App\Models\Activity'    => 'date_activite',
            'App\Models\Opportunity' => 'expected_close_date',
            'App\Models\Expense'     => 'date_frais',
            default                  => 'created_at',
        };
    }

    /**
     * Filtres métiers stricts pour s'assurer qu'on ne compte que ce qui est validé/réalisé.
     */
    private function applySpecificFilters($query, string $modelClass)
    {
        return match ($modelClass) {
            'App\Models\Activity'    => $query->where('statut', 'realisee'),
            'App\Models\Opportunity' => $query->where('status', 'won'),
            'App\Models\Expense'     => $query->where('statut', 'approuve'),
            default                  => $query,
        };
    }
}
