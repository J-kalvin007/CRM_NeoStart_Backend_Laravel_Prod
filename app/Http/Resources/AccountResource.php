<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'account_type_id' => $this->account_type_id,
            'nom'             => $this->nom,
            'adresse'       => $this->adresse,
            'ville'         => $this->ville,
            'quartier'      => $this->quartier,
            'telephone'     => $this->telephone,
            'email'         => $this->email,
            'responsable'   => $this->responsable,
            'gps_lat'       => $this->gps_lat,
            'gps_lng'       => $this->gps_lng,
            'potentiel'     => $this->potentiel,
            'score'         => $this->score,
            'statut'        => $this->statut,
            'statut_validation' => $this->statut_validation,
            'meta'          => $this->meta,
            'local_uuid'    => $this->local_uuid,
            'organizational_unit_id' => $this->organizational_unit_id,
            'user_id'       => $this->user_id,
            'delegue_id'    => $this->delegue_id,
            'type'          => $this->whenLoaded('accountType', fn() => [
                'id'      => $this->accountType->id,
                'code'    => $this->accountType->code,
                'libelle' => $this->accountType->libelle,
                'couleur' => $this->accountType->couleur,
                'icone'   => $this->accountType->icone,
            ]),
            'organizational_unit' => $this->whenLoaded('organizationalUnit', fn() => [
                'id'   => $this->organizationalUnit->id,
                'name' => $this->organizationalUnit->name,
            ]),
            'user' => $this->whenLoaded('user', fn() => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'delegue' => $this->whenLoaded('delegue', fn() => [
                'id'   => $this->delegue->id,
                'name' => $this->delegue->name,
            ]),
            'categories'    => $this->whenLoaded('categories', fn() =>
                $this->categories->map(fn($c) => ['id' => $c->id, 'nom' => $c->nom, 'type' => $c->type])
            ),
            'contacts_count' => $this->whenCounted('contacts'),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'deleted_at'    => $this->deleted_at,
        ];
    }
}
