<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_uuid' => $this->local_uuid,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'speciality' => $this->whenLoaded('speciality', fn() => ['id' => $this->speciality->id, 'nom' => $this->speciality->nom]),
            'segmentation' => $this->whenLoaded('segmentation', fn() => ['id' => $this->segmentation->id, 'nom' => $this->segmentation->nom]),
            'potential' => $this->whenLoaded('potential', fn() => ['id' => $this->potential->id, 'nom' => $this->potential->nom]),
            'influence' => $this->whenLoaded('influence', fn() => ['id' => $this->influence->id, 'nom' => $this->influence->nom]),
            'account' => $this->whenLoaded('account', fn() => ['id' => $this->account->id, 'nom' => $this->account->nom]),
            'subsidiary_id' => $this->subsidiary_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
