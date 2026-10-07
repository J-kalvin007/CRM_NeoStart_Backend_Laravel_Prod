<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'account_id'      => 'nullable|uuid|exists:accounts,id',
            'contact_type_id' => 'nullable|uuid|exists:contact_types,id',
            'speciality_id'   => 'nullable|uuid|exists:specialities,id',
            'nom'             => 'required|string|max:100',
            'prenom'          => 'required|string|max:100',
            'titre'           => 'nullable|string|max:50',
            // Coordonnées
            'telephone'       => 'nullable|string|max:30',
            'whatsapp'        => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:255',
            'ville'           => 'nullable|string|max:100',
            'zone_quartier'   => 'nullable|string|max:150',
            'adresse'         => 'nullable|string|max:255',
            'gps_lat'         => 'nullable|numeric|between:-90,90',
            'gps_lng'         => 'nullable|numeric|between:-180,180',
            // Ciblage
            'potentiel'       => 'nullable|in:faible,moyen,fort',
            'specialite'      => 'nullable|string|max:100',
            'nb_patients_cible_mois'       => 'nullable|integer|min:0|max:9999',
            'nb_visites_recommandees_mois' => 'nullable|integer|min:0|max:99',
            // Produits
            'produit_prioritaire'   => 'nullable|string|max:100',
            'produit_secondaire'    => 'nullable|string|max:100',
            'autre_produit_interet' => 'nullable|string|max:100',
            // Relation
            'canal_prefere'   => 'nullable|string|max:50',
            'statut_relation' => 'nullable|string|max:50',
            // Divers
            'statut'          => 'nullable|string|max:50',
            'meta'            => 'nullable|array',
            'local_uuid'      => 'nullable|uuid|unique:contacts,local_uuid',
            'code_externe'    => 'nullable|string|max:50|unique:contacts,code_externe',
        ];
    }
}
