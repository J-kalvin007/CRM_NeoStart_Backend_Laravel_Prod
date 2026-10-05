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
            'start_time'     => $this->start_time,
            'end_time'       => $this->end_time,
            'notes'          => $this->notes,
            'prochain_rdv'   => $this->prochain_rdv,
            'statut'         => $this->statut,
            'meta'           => $this->meta ?? null,
            // Visite DUO (superviseur participant, nature et réponses)
            'is_duo'                       => (bool) $this->is_duo,
            'duo_user'                     => $this->when($this->is_duo, fn () => $this->duoUser ? ['id' => (string) $this->duoUser->id, 'name' => $this->duoUser->name] : null),
            'duo_type'                     => $this->when($this->is_duo, fn () => $this->duoActivityType?->name),
            'duo_activity_type'             => $this->when($this->is_duo, fn () => $this->duoActivityType?->name),
            'is_trio'                      => $this->isTrio(),
            'trio_sales_manager'           => $this->when($this->isTrio() && $this->relationLoaded('accompanyingUser'), fn () => $this->accompanyingUser ? ['id' => (string) $this->accompanyingUser->id, 'name' => $this->accompanyingUser->name] : null),
            'duo_status'                   => $this->duo_status,
            'duo_delegue_status'           => $this->duo_delegue_status,
            'duo_delegue_rejection_reason' => $this->duo_delegue_rejection_reason,
            'is_locked'      => $this->is_locked,
            'report_status'  => $this->report_status,
            'report_content' => $this->report_content,
            'validation_date'=> $this->validation_date,
            'rejection_reason'=> $this->rejection_reason,
            'signature'      => $this->signature,
            'subsidiary_id'  => $this->subsidiary_id,
            'user_id'        => $this->user_id,
            'delegate_name'  => $this->delegue?->name,
            'type'           => $this->whenLoaded('type', fn() => ['id' => $this->type->id, 'nom' => $this->type->nom]),
            'user'           => $this->whenLoaded('delegue', fn() => ['id' => $this->delegue->id, 'name' => $this->delegue->name, 'email' => $this->delegue->email]),
            'delegue'        => $this->whenLoaded('delegue', fn() => [
                'id'    => $this->delegue->id,
                'name'  => $this->delegue->name,
                'email' => $this->delegue->email,
                'manager_id' => $this->delegue->manager_id,
            ]),
            'contact'        => new ContactResource($this->whenLoaded('contact')),
            'contacts'       => ContactResource::collection($this->whenLoaded('contacts')),
            'account'        => new AccountResource($this->whenLoaded('account')),
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
            // Compte-rendu : résultat global et prochaine action saisis par le délégué
            'resultat'       => $this->meta['resultat'] ?? null,
            'actions'        => $this->meta['prochaine_action'] ?? null,
            'objections'     => $this->whenLoaded('objections', fn() =>
                $this->objections->map(fn($o) => [
                    'objection_id'         => $o->id,
                    'title'                => $o->title,
                    'product_id'           => $o->product_id,
                    'product_name'         => $o->product?->nom,
                    'recommended_argument' => $o->recommended_argument,
                    'physician_reaction'   => $o->pivot->physician_reaction,
                    'notes'                => $o->pivot->notes,
                ])
            ),
            // Échantillons remis lors de la visite (mouvements de stock de distribution)
            'samples'        => $this->whenLoaded('sampleMovements', fn() =>
                $this->sampleMovements
                    ->filter(fn($m) => ($m->movement_type ?? 'distribution') === 'distribution')
                    ->values()
                    ->map(fn($m) => [
                        'product_id'   => $m->product_id,
                        'product_name' => $m->product?->nom,
                        'quantity'     => abs((int) $m->quantity),
                        'lot_number'   => $m->lot_number,
                    ])
            ),
            'sample_movements' => $this->whenLoaded('sampleMovements', fn() =>
                $this->sampleMovements->map(fn($movement) => [
                    'id' => $movement->id,
                    'product_id' => $movement->product_id,
                    'product_name' => $movement->product?->nom,
                    'quantity' => $movement->quantity,
                    'lot_number' => $movement->lot_number,
                    'contact_id' => $movement->contact_id,
                ])
            ),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'deleted_at'     => $this->deleted_at,
        ];
    }
}
