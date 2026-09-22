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
            'local_uuid'     => $this->local_uuid,
            'activity_type'  => $this->activity_type,
            'date_activite'  => $this->date_activite,
            'heure_debut'    => $this->heure_debut,
            'heure_fin'      => $this->heure_fin,
            'duree_minutes'  => $this->duree_minutes ?? $this->duration,
            'duration'       => $this->duration ?? $this->duree_minutes,
            'gps_debut'      => ['lat' => $this->gps_debut_lat, 'lng' => $this->gps_debut_lng],
            'gps_fin'        => ['lat' => $this->gps_fin_lat, 'lng' => $this->gps_fin_lng],
            'notes'          => $this->notes,
            'prochain_rdv'   => $this->prochain_rdv,
            'statut'         => $this->statut,
            'is_locked'      => $this->is_locked,
            'report_status'  => $this->report_status,
            'report_content' => $this->report_content,
            'validation_date'=> $this->validation_date,
            'rejection_reason'=> $this->rejection_reason,
            'signature'      => $this->signature,
            'subsidiary_id'  => $this->subsidiary_id,
            'user_id'        => $this->user_id,
            'delegate_name'  => $this->delegue?->name ?? 'Délégué Médical',
            'type'           => $this->whenLoaded('type', fn() => ['id' => $this->type->id, 'nom' => $this->type->nom]),
            'user'           => $this->whenLoaded('delegue', fn() => ['id' => $this->delegue->id, 'name' => $this->delegue->name, 'email' => $this->delegue->email]),
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
                'id'     => $this->tourPlan->id,
                'nom'    => $this->tourPlan->nom,
                'statut' => $this->tourPlan->statut,
            ]),
            'documents'      => $this->whenLoaded('documents', function() {
                return $this->documents->map(function($doc) {
                    return [
                        'id' => $doc->id,
                        'titre' => $doc->titre,
                        'duration_seconds' => $doc->pivot->duration_seconds,
                        'display_order' => $doc->pivot->display_order
                    ];
                });
            }),
            'products'       => $this->whenLoaded('products', fn() =>
                $this->products->map(fn($p) => [
                    'id'                => $p->id,
                    'nom'               => $p->nom,
                    'quantity'          => $p->pivot->quantity ?? 0,
                    'customer_interest' => $p->pivot->customer_interest ?? null,
                    'remarques'         => $p->pivot->remarques ?? null,
                ])
            ),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'deleted_at'     => $this->deleted_at,
        ];
    }
}
