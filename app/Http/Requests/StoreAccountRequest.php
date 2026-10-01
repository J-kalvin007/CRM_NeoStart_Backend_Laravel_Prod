<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'organizational_unit_id' => 'nullable|uuid|exists:organizational_units,id',
            'account_type_id' => 'nullable|uuid|exists:account_types,id',
            'nom'             => 'required|string|max:255',
            'adresse'         => 'nullable|string',
            'ville'           => 'nullable|string|max:100',
            'quartier'        => 'nullable|string|max:100',
            'gps_lat'         => 'required|numeric|between:-90,90',
            'gps_lng'         => 'required|numeric|between:-180,180',
            'telephone'       => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:255',
            'responsable'     => 'nullable|string|max:255',
            'potentiel'       => 'nullable|in:faible,moyen,fort',
            'statut'          => 'in:en_attente,actif,inactif,suspendu,rejete',
            'meta'            => 'nullable|array',
            'local_uuid'      => 'nullable|uuid|unique:accounts,local_uuid',
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
