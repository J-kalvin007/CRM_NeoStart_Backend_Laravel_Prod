<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_id'      => 'nullable|string',
            'accounts'        => 'nullable|array',
            'accounts.*'      => 'nullable|string',
            'account_ids'     => 'nullable|array',
            'account_ids.*'   => 'nullable|string',
            'attached_account_ids' => 'nullable|array',
            'attached_account_ids.*' => 'nullable|string',
            'primary_account_id' => 'nullable|string',
            'etablissement'   => 'nullable|string|max:255',
            'account_name'    => 'nullable|string|max:255',
            'attached_account_names' => 'nullable|array',
            'attached_account_names.*' => 'nullable|string',
            'contact_type_id' => 'nullable|uuid|exists:contact_types,id',
            'speciality_id'   => 'nullable|uuid|exists:specialities,id',
            'nom'             => 'sometimes|required|string|max:100',
            'prenom'          => 'sometimes|required|string|max:100',
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
            'influence'       => 'nullable|in:faible,moyen,fort',
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
            'statut'          => 'nullable|in:en_attente,actif,inactif,archive,rejete',
            'meta'            => 'nullable|array',
            'local_uuid'      => 'nullable|uuid|unique:contacts,local_uuid,' . $this->route('id'),
            'code_externe'    => 'nullable|string|max:50|unique:contacts,code_externe,' . $this->route('id'),
        ];
    }
}

