<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\SampleMovement;
use App\Notifications\ActivityAssignedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ActivityObserver
{
    /**
     * Handle the Activity "created" event.
     */
    public function created(Activity $activity): void
    {
        // Si l'activité est assignée à quelqu'un d'autre que le créateur, envoyer une notif
        if ($activity->user_id && $activity->user_id !== auth()->id()) {
            if ($activity->delegue) {
                $activity->delegue->notify(new ActivityAssignedNotification($activity));
            }
        }
    }

    /**
     * Handle the Activity "updated" event.
     */
    public function updated(Activity $activity): void
    {
        // 1. Gérer l'assignation (Changement de user_id)
        if ($activity->wasChanged('user_id') && $activity->user_id !== auth()->id()) {
            if ($activity->delegue) {
                $activity->delegue->notify(new ActivityAssignedNotification($activity));
            }
        }

        // 2. Le Workflow de réalisation
        if ($activity->wasChanged('statut') && $activity->statut === 'realisee') {
            
            // Calcul de la durée si on a début et fin
            if ($activity->heure_debut && $activity->heure_fin && !$activity->duree_minutes) {
                $debut = Carbon::parse($activity->heure_debut);
                $fin = Carbon::parse($activity->heure_fin);
                $activity->duree_minutes = abs($debut->diffInMinutes($fin, false));
            }

            // Vérification GPS (Alerte / Drapeau)
            if ($activity->gps_fin_lat && $activity->gps_fin_lng) {
                $targetLat = $activity->contact?->gps_lat ?? $activity->account?->gps_lat;
                $targetLng = $activity->contact?->gps_lng ?? $activity->account?->gps_lng;

                if ($targetLat && $targetLng) {
                    $distance = $this->calculateDistance($activity->gps_fin_lat, $activity->gps_fin_lng, $targetLat, $targetLng);
                    if ($distance > 0.5) { // Si > 500 mètres
                        $activity->gps_flagged = true;
                    }
                }
            }

            // Verrouillage de l'activité
            $activity->is_locked = true;
            $activity->locked_at = now();
            
            // Save en silence pour éviter de redéclencher l'événement updated
            $activity->saveQuietly();

            // Décrémentation des échantillons (SampleMovements)
            $this->deductSamples($activity);

            // -------------------------------------------------------------
            // Recalcul des objectifs associés (Indicateurs)
            // -------------------------------------------------------------
            app(\App\Services\IndicatorCalculationEngine::class)->updateObjectivesForModel($activity);
        }
    }

    /**
     * Déduit les stocks du délégué pour les produits distribués.
     */
    private function deductSamples(Activity $activity): void
    {
        $products = $activity->products;
        
        foreach ($products as $product) {
            $quantity = $product->pivot->quantity ?? 0;
            if ($quantity > 0) {
                SampleMovement::create([
                    'user_id' => $activity->user_id,
                    'product_id' => $product->id,
                    'activity_id' => $activity->id,
                    'movement_type' => 'distribution',
                    'quantity' => $quantity,
                    'remarques' => 'Visite Clôturée',
                ]);
            }
        }
    }

    /**
     * Calcul de distance GPS (Formule de Haversine) en kilomètres
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        return ($miles * 1.609344);
    }
}
