<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $selected = \App\Support\CountryFilter::selectedValue();
        $hasSelection = $selected && !in_array($selected, ['group', 'default'], true);
        $countryId = $hasSelection ? \App\Support\CountryFilter::resolveCountryId($selected) : null;
        $countryPivot = null;
        if ($this->relationLoaded('countries') && $this->countries->isNotEmpty()) {
            $countryPivot = $hasSelection ? $this->countries->firstWhere('id', $countryId) : $this->countries->first();
        }

        return [
            'id'             => $this->id,
            'countries'      => $this->whenLoaded('countries', fn() => $this->countries->when($hasSelection, fn ($countries) => $countries->where('id', $countryId))->values()->map(function ($c) {
                return [
                    'id' => $c->id,
                    'country_id' => $c->id,
                    'name' => $c->name,
                    'iso_code' => $c->iso_code,
                    'pght' => $c->pivot->pght,
                    'prix_cession' => $c->pivot->prix_cession,
                    'ppv' => $c->pivot->ppv,
                    'devise' => $c->pivot->devise,
                    'stock_actuel' => $c->pivot->stock_actuel,
                    'stock_alerte' => $c->pivot->stock_alerte,
                    'stock_critique' => $c->pivot->stock_actuel <= $c->pivot->stock_alerte,
                ];
            })),
            'pght'           => $countryPivot?->pivot->pght ?? null,
            'prix_cession'   => $countryPivot?->pivot->prix_cession ?? null,
            'ppv'            => $countryPivot?->pivot->ppv ?? null,
            'devise'         => $countryPivot?->pivot->devise ?? 'XOF',
            'stock_actuel'   => $countryPivot?->pivot->stock_actuel ?? 0,
            'stock_alerte'   => $countryPivot?->pivot->stock_alerte ?? 0,
            'stock_critique' => ($countryPivot?->pivot->stock_actuel ?? 0) <= ($countryPivot?->pivot->stock_alerte ?? 0),
            'nom'            => $this->nom,
            'code'           => $this->code,
            'marque'         => $this->marque,
            'gamme'          => $this->gamme,
            'description'    => $this->description,
            'image_url'      => $this->image_url,
            'statut'         => $this->statut,
            'dci'            => $this->dci,
            'conditionnement'=> $this->conditionnement,
            'indications'    => $this->indications,
            'posologie'      => $this->posologie,
            'effets_secondaires' => $this->effets_secondaires,
            'meta'           => $this->meta,  // Attributs sectoriels libres (molecule, dosage, AMM...)
            'galenic_form' => $this->whenLoaded('galenicForm', fn() => ['id' => $this->galenicForm->id, 'name' => $this->galenicForm->name]),
            'documents' => $this->whenLoaded('documents'),
            'product_type'   => $this->whenLoaded('productType', fn() => [
                'id'              => $this->productType->id,
                'code'            => $this->productType->code,
                'libelle'         => $this->productType->libelle,
                'is_stockable'    => $this->productType->is_stockable,
                'is_distributable'=> $this->productType->is_distributable,
            ]),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'deleted_at'     => $this->deleted_at,
        ];
    }
}
