<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObjectifResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                if (!$this->user) return null;
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'country_id' => $this->user->country_id,
                    'country' => $this->user->country ? [
                        'id' => $this->user->country->id,
                        'name' => $this->user->country->name,
                    ] : null,
                ];
            }),
            'roles' => $this->user?->relationLoaded('roles') ? $this->user->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values() : [],
            'country_id' => $this->country_id ?? $this->user?->country_id,
            'country_name' => $this->country?->name ?? $this->user?->country?->name,
            'indicator_id' => $this->indicator_id,
            'indicator' => $this->whenLoaded('indicator'),
            'objectif_type_id' => $this->objectif_type_id,
            'type' => $this->whenLoaded('type'),
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product'),
            'assigned_by_user_id' => $this->assigned_by_user_id,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'periode' => $this->periode,
            'valeur_cible' => $this->valeur_cible,
            'valeur_realisee' => $this->valeur_realisee,
            'taux_realisation' => $this->taux_realisation,
            'description' => $this->description,
            'statut' => $this->statut,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
