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
            'id'              => $this->id,
            'local_uuid'      => $this->local_uuid,
            'nom'             => $this->nom,
            'prenom'          => $this->prenom,
            'nom_complet'     => trim("{$this->titre} {$this->prenom} {$this->nom}"),
            'titre'           => $this->titre,
            'telephone'       => $this->telephone,
            'email'           => $this->email,
            'gps_lat'         => $this->gps_lat,
            'gps_lng'         => $this->gps_lng,
            'potentiel'       => $this->potentiel,
            'influence'       => $this->influence,
            'score'           => $this->score,
            'statut'          => $this->statut,
            'statut_validation' => $this->statut_validation,
            'meta'            => $this->meta,
            'specialite'      => $specialite,
            'account_id'      => $this->account_id,
            'contact_type_id' => $this->contact_type_id,
            'contact_type'    => $this->whenLoaded('contactType', fn() => [
                'id'      => $this->contactType->id,
                'code'    => $this->contactType->code,
                'libelle' => $this->contactType->libelle,
                'couleur' => $this->contactType->couleur,
                'icone'   => $this->contactType->icone,
            ]),
            'speciality'   => $this->whenLoaded('speciality', fn() => ['id' => $this->speciality->id, 'nom' => $this->speciality->nom]),
            'segmentation' => $this->whenLoaded('segmentation', fn() => ['id' => $this->segmentation->id, 'nom' => $this->segmentation->nom]),
            'potential'    => $this->whenLoaded('potential', fn() => ['id' => $this->potential->id, 'nom' => $this->potential->nom]),
            'account'         => $this->whenLoaded('account', fn() => [
                'id'  => $this->account->id,
                'nom' => $this->account->nom,
                'type' => $this->account->type ?? 'Clinique',
                'adresse' => $this->account->adresse,
            ]),
            'account_name' => $this->account?->nom ?? data_get($this->meta, 'etablissement') ?? 'Établissement de Santé',
            'adresse' => $this->account?->adresse ?? data_get($this->meta, 'adresse') ?? 'Abidjan / Lomé',
            'subsidiary_id' => $this->subsidiary_id,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
