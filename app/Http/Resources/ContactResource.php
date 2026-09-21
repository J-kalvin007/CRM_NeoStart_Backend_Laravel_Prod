<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
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
            'meta'            => $this->meta,
            'local_uuid'      => $this->local_uuid,
            'account_id'      => $this->account_id,
            'contact_type_id' => $this->contact_type_id,
            'contact_type'    => $this->whenLoaded('contactType', fn() => [
                'id'      => $this->contactType->id,
                'code'    => $this->contactType->code,
                'libelle' => $this->contactType->libelle,
                'couleur' => $this->contactType->couleur,
                'icone'   => $this->contactType->icone,
            ]),
            'account'         => $this->whenLoaded('account', fn() => [
                'id'  => $this->account->id,
                'nom' => $this->account->nom,
            ]),
            'categories'      => $this->whenLoaded('categories', fn() =>
                $this->categories->map(fn($c) => ['id' => $c->id, 'nom' => $c->nom])
            ),
            'activities_count' => $this->whenCounted('activities'),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
