<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
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
            'organizational_unit_id' => 'sometimes|required|uuid|exists:organizational_units,id',
            'account_type_id' => 'nullable|uuid|exists:account_types,id',
            'user_id'         => 'nullable|uuid|exists:users,id',
            'delegue_id'      => 'nullable|uuid|exists:users,id',
            'nom'             => 'sometimes|required|string|max:255',
            'adresse'         => 'nullable|string',
            'ville'           => 'nullable|string|max:100',
            'quartier'        => 'nullable|string|max:100',
            'gps_lat'         => 'nullable|numeric|between:-90,90',
            'gps_lng'         => 'nullable|numeric|between:-180,180',
            'telephone'       => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:255',
            'responsable'     => 'nullable|string|max:255',
            'potentiel'       => 'nullable|in:faible,moyen,fort',
            'statut'          => 'in:en_attente,valide,actif,inactif,suspendu,rejete',
            'meta'            => 'nullable|array',
            'local_uuid'      => 'nullable|uuid',
        ];
    }
}

