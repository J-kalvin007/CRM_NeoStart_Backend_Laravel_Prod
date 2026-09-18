<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $specialite = $this->specialite 
            ?? $this->speciality?->nom 
            ?? data_get($this->meta, 'specialite') 
            ?? $this->titre;

        return [
            'id' => $this->id,
            'local_uuid' => $this->local_uuid,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'titre' => $this->titre,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'gps_lat' => $this->gps_lat,
            'gps_lng' => $this->gps_lng,
            'score' => $this->score ?? 85,
            'statut' => $this->statut ?? 'actif',
            'meta' => $this->meta,
            'specialite' => $specialite,
            'speciality' => $this->whenLoaded('speciality', fn() => ['id' => $this->speciality->id, 'nom' => $this->speciality->nom]),
            'segmentation' => $this->whenLoaded('segmentation', fn() => ['id' => $this->segmentation->id, 'nom' => $this->segmentation->nom]),
            'potential' => $this->whenLoaded('potential', fn() => ['id' => $this->potential->id, 'nom' => $this->potential->nom]),
            'influence' => $this->whenLoaded('influence', fn() => ['id' => $this->influence->id, 'nom' => $this->influence->nom]),
            'account' => $this->account ? [
                'id' => $this->account->id,
                'nom' => $this->account->nom,
                'type' => $this->account->type ?? 'Clinique',
                'adresse' => $this->account->adresse,
            ] : null,
            'account_name' => $this->account?->nom ?? data_get($this->meta, 'etablissement') ?? 'Établissement de Santé',
            'adresse' => $this->account?->adresse ?? data_get($this->meta, 'adresse') ?? 'Abidjan / Lomé',
            'contact_type' => $this->whenLoaded('contactType', fn() => [
                'id' => $this->contactType->id,
                'code' => $this->contactType->code,
                'libelle' => $this->contactType->libelle,
            ]),
            'subsidiary_id' => $this->subsidiary_id,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
