<?php

namespace App\Observers;

use App\Models\Task;
use App\Models\Objectif;
use Carbon\Carbon;

class TaskObserver
{
    /**
     * Handle the Task "saved" event (covers created and updated).
     */
    public function saved(Task $task): void
    {
        // Si la tâche vient de passer à 'completed' et que c'est une visite
        if ($task->isDirty('status') && $task->status === 'completed' && $task->type === 'visit') {
            
            $now = Carbon::now();
            
            // Trouver l'objectif actif de l'utilisateur pour cette période (mensuel)
            $objectif = Objectif::where('user_id', $task->user_id)
                ->where('type_objectif', 'visites') // Exemple: type d'objectif
                ->where('date_debut', '<=', $now)
                ->where('date_fin', '>=', $now)
                ->first();

            if ($objectif) {
                // Incrémenter la valeur réalisée
                $objectif->valeur_realisee += 1;
                $objectif->recalculerTaux(); // Recalcule le % et sauvegarde
            }
        }
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleted(Task $task): void
    {
        // Cas rare: si une visite validée est supprimée, on devrait décrémenter
        if ($task->status === 'completed' && $task->type === 'visit') {
            $now = Carbon::now();
            $objectif = Objectif::where('user_id', $task->user_id)
                ->where('type_objectif', 'visites')
                ->where('date_debut', '<=', $now)
                ->where('date_fin', '>=', $now)
                ->first();

            if ($objectif && $objectif->valeur_realisee > 0) {
                $objectif->valeur_realisee -= 1;
                $objectif->recalculerTaux();
            }
        }
    }
}
