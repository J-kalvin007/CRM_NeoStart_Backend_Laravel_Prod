<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTourPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom'                    => 'sometimes|string|max:255',
            'description'            => 'nullable|string',
            'date_debut'             => 'sometimes|date',
            'date_fin'               => 'sometimes|date',
            'statut'                 => 'sometimes|in:brouillon,soumis,valide,en_cours,termine,rejete',
            'commentaire_validation' => 'nullable|string',
        ];
    }
}
