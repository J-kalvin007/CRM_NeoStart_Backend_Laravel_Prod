<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTourPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subsidiary_id' => 'nullable|uuid|exists:subsidiaries,id',
            'nom'           => 'required|string|max:255',
            'description'   => 'nullable|string',
            'date_debut'    => 'required|date',
            'date_fin'      => 'required|date',
            'statut'        => 'nullable|in:brouillon,soumis,valide,en_cours,termine,rejete',
            'local_uuid'    => 'nullable|uuid',
            'activity_ids'  => 'nullable|array',
            'activity_ids.*'=> 'string',
        ];
    }
}
