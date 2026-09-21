<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'nom'            => $this->nom,
            'code'           => $this->code,
            'marque'         => $this->marque,
            'gamme'          => $this->gamme,
            'prix_unitaire'  => $this->prix_unitaire,
            'pght'           => $this->pght,
            'ppv'            => $this->ppv,
            'devise'         => $this->devise,
            'stock_actuel'   => $this->stock_actuel,
            'stock_alerte'   => $this->stock_alerte,
            'stock_critique' => $this->stock_actuel <= $this->stock_alerte,
            'description'    => $this->description,
            'image_url'      => $this->image_url,
            'statut'         => $this->statut,
            'meta'           => $this->meta,  // Attributs sectoriels libres (molecule, dosage, AMM...)
            'product_type'   => $this->whenLoaded('productType', fn() => [
                'id'              => $this->productType->id,
                'code'            => $this->productType->code,
                'libelle'         => $this->productType->libelle,
                'is_stockable'    => $this->productType->is_stockable,
                'is_distributable'=> $this->productType->is_distributable,
            ]),
            'subsidiary_id'  => $this->subsidiary_id,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
