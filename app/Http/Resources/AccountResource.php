<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Résoudre le type d'établissement depuis la relation accountType ou propriété type/meta
        $accountTypeLabel = null;
        if ($this->accountType) {
            $accountTypeLabel = $this->accountType->libelle ?? $this->accountType->name ?? $this->accountType->code;
        }
        if (empty($accountTypeLabel)) {
            $accountTypeLabel = $this->type ?? data_get($this->meta, 'type');
        }

        // Concaténer le type et le nom de l'établissement si le type existe
        $nomAffichage = $this->nom;
        if (!empty($accountTypeLabel) && is_string($accountTypeLabel) && trim($accountTypeLabel) !== '') {
            $typeClean = trim($accountTypeLabel);
            if ($nomAffichage && !str_starts_with(mb_strtolower($nomAffichage), mb_strtolower($typeClean))) {
                $nomAffichage = $typeClean . ' ' . $nomAffichage;
            }
        }

        // Résoudre le potentiel depuis la relation potential
        $potentielLabel = null;
        if ($this->relationLoaded('potential') && $this->potential) {
            $potentielLabel = $this->potential->name ?? $this->potential->code;
        }

        return [
            'id'              => $this->id,
            'account_type_id' => $this->account_type_id,
            'account_type'    => $accountTypeLabel, // string pour le mobile
            'nom'             => $nomAffichage,
            'adresse'         => $this->adresse,
            'ville'           => $this->ville,
            'quartier'        => $this->quartier,
            'zone_quartier'   => $this->zone_quartier,
            'telephone'       => $this->telephone,
            'whatsapp'        => $this->whatsapp,
            'email'           => $this->email,
            'responsable'     => $this->responsable,
            'responsable_prenom' => $this->responsable_prenom,
            'responsable_nom'    => $this->responsable_nom,
            'gps_lat'         => $this->gps_lat,
            'gps_lng'         => $this->gps_lng,
            'potentiel'       => $potentielLabel,
            'potentiel_commande_mois' => $this->potentiel_commande_mois,
            'grossiste_principal' => $this->grossiste_principal,
            'produits_disponibles' => $this->produits_disponibles ?? [],
            'score'           => $this->score,
            'statut'          => $this->statut,
            'statut_validation' => $this->statut_validation,
            'code_externe'    => $this->code_externe,
            'meta'            => $this->meta,
            'local_uuid'      => $this->local_uuid,
            'country_id' => $this->country_id,
            'user_id'         => $this->user_id,
            'delegue_id'      => $this->delegue_id,
            'type'            => $this->whenLoaded('accountType', fn() => [
                'id'      => $this->accountType->id,
                'code'    => $this->accountType->code,
                'libelle' => $this->accountType->libelle,
                'couleur' => $this->accountType->couleur,
                'icone'   => $this->accountType->icone,
            ]),
            'potential'       => $this->whenLoaded('potential', fn() => [
                'id'   => $this->potential->id,
                'name' => $this->potential->name,
                'code' => $this->potential->code,
            ]),
            'country' => $this->whenLoaded('country', fn() => [
                'id'   => $this->country->id,
                'name' => $this->country->name,
            ]),
            'user'    => $this->whenLoaded('user', fn() => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'delegue' => $this->whenLoaded('delegue', fn() => [
                'id'   => $this->delegue->id,
                'name' => $this->delegue->name,
            ]),
            'categories' => $this->whenLoaded('categories', fn() =>
                $this->categories->map(fn($c) => ['id' => $c->id, 'nom' => $c->nom, 'type' => $c->type])
            ),
            'contacts'       => $this->whenLoaded('contacts', fn() => ContactResource::collection($this->contacts)),
            'contacts_count' => $this->whenCounted('contacts'),
            'submitted_by'   => $this->whenLoaded('user', fn() => $this->user->name),
            'delegue_name'   => $this->whenLoaded('delegue', fn() => $this->delegue->name),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'deleted_at'     => $this->deleted_at,
        ];
    }
}
