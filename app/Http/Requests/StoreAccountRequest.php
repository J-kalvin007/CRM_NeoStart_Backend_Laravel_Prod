<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'country_id' => 'nullable|uuid|exists:countries,id',
            'account_type_id' => 'nullable|uuid|exists:account_types,id',
            'nom'             => 'required|string|max:255',
            'adresse'         => 'nullable|string',
            'ville'           => 'nullable|string|max:100',
            'quartier'        => 'nullable|string|max:100',
            'gps_lat'         => 'nullable|numeric|between:-90,90',
            'gps_lng'         => 'nullable|numeric|between:-180,180',
            'telephone'       => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:255',
            'responsable'     => 'nullable|string|max:255',
            'potentiel'       => 'nullable|in:faible,moyen,fort',
            'statut'          => 'in:en_attente,actif,inactif,suspendu,rejete',
            'meta'            => 'nullable|array',
            'local_uuid'      => 'nullable|uuid|unique:accounts,local_uuid',
            'contact_ids'     => 'nullable|array',
            'contact_ids.*'   => 'nullable|uuid|exists:contacts,id',
            'delegue_ids'     => 'nullable|array',
            'delegue_ids.*'   => 'nullable|uuid|exists:users,id',
            'delegue_id'      => 'nullable|uuid|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'gps_lat.required' => 'Les coordonnées GPS (latitude) sont obligatoires.',
            'gps_lng.required' => 'Les coordonnées GPS (longitude) sont obligatoires.',
        ];
    }
}
