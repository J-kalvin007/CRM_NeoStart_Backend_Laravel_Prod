<?php

namespace App\Observers;

use App\Models\Campaign;
use Illuminate\Validation\ValidationException;

class CampaignObserver
{
    /**
     * Le flux linéaire de la campagne.
     * Chaque clé représente le statut ACTUEL. Le tableau représente les statuts SUIVANTS autorisés.
     */
    private array $allowedTransitions = [
        'brouillon'   => ['validation'],
        'validation'  => ['brouillon', 'active'],
        'active'      => ['suspendue', 'terminee'],
        'suspendue'   => ['active', 'terminee'],
        'terminee'    => [],
    ];

    /**
     * Handle the Campaign "updating" event.
     */
    public function updating(Campaign $campaign): void
    {
        if ($campaign->isDirty('statut')) {
            $oldStatus = $campaign->getOriginal('statut') ?? 'brouillon';
            $newStatus = $campaign->statut;

            // Si le statut ne change pas vraiment ou que l'ancien n'est pas dans la liste
            if ($oldStatus === $newStatus) {
                return;
            }

            if (!isset($this->allowedTransitions[$oldStatus])) {
                return; // Statut inconnu, on ne bloque pas au cas où (legacy data)
            }

            $allowedNext = $this->allowedTransitions[$oldStatus];

            if (!in_array($newStatus, $allowedNext)) {
                throw ValidationException::withMessages([
                    'statut' => "Transition invalide : impossible de passer de '{$oldStatus}' à '{$newStatus}'."
                ]);
            }
        }
    }
}
