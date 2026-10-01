<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateObjectifRequest extends FormRequest
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
            'user_id' => 'sometimes|nullable|exists:users,id',
            'objectif_type_id' => 'sometimes|uuid|exists:objectif_types,id',
            'indicator_id' => 'sometimes|nullable|uuid|exists:indicators,id',
            'date_debut' => 'sometimes|date',
            'date_fin' => 'sometimes|date',
            'periode' => 'sometimes|nullable|string',
            'valeur_cible' => 'sometimes|numeric|min:0',
            'valeur_realisee' => 'sometimes|nullable|numeric|min:0',
            'product_id' => 'sometimes|nullable|uuid|exists:products,id',
            'description' => 'sometimes|nullable|string',
            'statut' => 'sometimes|string|in:en_cours,actif,atteint,depasse,non_atteint,annule,en_attente,valide,rejete',
            'motif_rejet' => 'sometimes|nullable|string',
        ];
    }
}

