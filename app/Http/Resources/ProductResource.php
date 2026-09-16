<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_uuid' => $this->local_uuid,
            'nom' => $this->nom,
            'code' => $this->code,
            'description' => $this->description,
            'galenic_form' => $this->whenLoaded('galenicForm', fn() => ['id' => $this->galenicForm->id, 'nom' => $this->galenicForm->nom]),
            'stock_actuel' => $this->stock_actuel,
            'stock_alerte' => $this->stock_alerte,
            'prix_unitaire' => $this->prix_unitaire,
            'documents' => $this->whenLoaded('documents'),
            'subsidiary_id' => $this->subsidiary_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
