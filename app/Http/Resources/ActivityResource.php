<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'activity_type'  => $this->activity_type,
            'date_activite'  => $this->date_activite,
            'heure_debut'    => $this->heure_debut,
            'heure_fin'      => $this->heure_fin,
            'duree_minutes'  => $this->duree_minutes,
            'gps_debut'      => ['lat' => $this->gps_debut_lat, 'lng' => $this->gps_debut_lng],
            'gps_fin'        => ['lat' => $this->gps_fin_lat, 'lng' => $this->gps_fin_lng],
            'notes'          => $this->notes,
            'prochain_rdv'   => $this->prochain_rdv,
            'statut'         => $this->statut,
            'is_locked'      => $this->is_locked,
            'local_uuid'     => $this->local_uuid,
            'delegue'        => $this->whenLoaded('delegue', fn() => [
                'id'    => $this->delegue->id,
                'name'  => $this->delegue->name,
                'email' => $this->delegue->email,
            ]),
            'contact'        => $this->whenLoaded('contact', fn() => [
                'id'         => $this->contact->id,
                'nom_complet'=> trim("{$this->contact->titre} {$this->contact->prenom} {$this->contact->nom}"),
                'titre'      => $this->contact->titre,
                'nom'        => $this->contact->nom,
                'prenom'     => $this->contact->prenom,
                'potentiel'  => $this->contact->potentiel,
                'meta'       => $this->contact->meta,
                'specialite' => $this->contact->specialite,
            ]),
            'account'        => $this->whenLoaded('account', fn() => [
                'id'  => $this->account->id,
                'nom' => $this->account->nom,
            ]),
            'tour_plan'      => $this->whenLoaded('tourPlan', fn() => [
                'id'  => $this->tourPlan->id,
                'nom' => $this->tourPlan->nom,
            ]),
            'products'       => $this->whenLoaded('products', fn() =>
                $this->products->map(fn($p) => [
                    'id'                => $p->id,
                    'nom'               => $p->nom,
                    'quantity'          => $p->pivot->quantity,
                    'customer_interest' => $p->pivot->customer_interest,
                    'remarques'         => $p->pivot->remarques,
                ])
            ),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
