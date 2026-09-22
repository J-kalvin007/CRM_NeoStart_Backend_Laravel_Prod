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
            'account_id'      => 'nullable|uuid|exists:accounts,id',
            'contact_type_id' => 'nullable|uuid|exists:contact_types,id',
            'nom'             => 'sometimes|required|string|max:100',
            'prenom'          => 'sometimes|required|string|max:100',
            'titre'           => 'nullable|string|max:50',
            'telephone'       => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:255',
            'gps_lat'         => 'nullable|numeric|between:-90,90',
            'gps_lng'         => 'nullable|numeric|between:-180,180',
            'potentiel'       => 'nullable|in:faible,moyen,fort',
            'influence'       => 'nullable|in:faible,moyen,fort',
            'statut'          => 'nullable|in:en_attente,actif,inactif,archive,rejete',
            'meta'            => 'nullable|array',
            'local_uuid'      => 'nullable|uuid|unique:contacts,local_uuid,' . $this->route('id'),
        ];
    }
}

