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
            'nom'             => 'required|string|max:100',
            'prenom'          => 'required|string|max:100',
            'titre'           => 'nullable|string|max:50',
            'telephone'       => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:255',
            'gps_lat'         => 'nullable|numeric|between:-90,90',
            'gps_lng'         => 'nullable|numeric|between:-180,180',
            'potentiel'       => 'nullable|in:faible,moyen,fort',
            'influence'       => 'nullable|in:faible,moyen,fort',
            'statut'          => 'in:actif,inactif,archive',
            'meta'            => 'nullable|array',
            'local_uuid'      => 'nullable|uuid|unique:contacts,local_uuid',
        ];
    }
}
