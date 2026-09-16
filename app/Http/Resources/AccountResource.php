<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_uuid' => $this->local_uuid,
            'nom' => $this->nom,
            'type' => $this->type,
            'adresse' => $this->adresse,
            'gps_lat' => $this->gps_lat,
            'gps_lng' => $this->gps_lng,
            'potential' => $this->whenLoaded('potential', fn() => ['id' => $this->potential->id, 'nom' => $this->potential->nom]),
            'subsidiary_id' => $this->subsidiary_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
