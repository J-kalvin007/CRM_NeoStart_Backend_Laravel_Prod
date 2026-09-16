<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_uuid' => $this->local_uuid,
            'date_activite' => $this->date_activite,
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            'duration' => $this->duration,
            'statut' => $this->statut,
            'type' => $this->whenLoaded('type', fn() => ['id' => $this->type->id, 'nom' => $this->type->nom]),
            'report_status' => $this->report_status,
            'report_content' => $this->report_content,
            'validation_date' => $this->validation_date,
            'rejection_reason' => $this->rejection_reason,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'account' => new AccountResource($this->whenLoaded('account')),
            'documents' => $this->whenLoaded('documents', function() {
                return $this->documents->map(function($doc) {
                    return [
                        'id' => $doc->id,
                        'titre' => $doc->titre,
                        'duration_seconds' => $doc->pivot->duration_seconds,
                        'display_order' => $doc->pivot->display_order
                    ];
                });
            }),
            'products' => $this->whenLoaded('products'),
            'subsidiary_id' => $this->subsidiary_id,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
