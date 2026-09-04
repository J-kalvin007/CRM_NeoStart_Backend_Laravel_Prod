<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTourPlanRequest extends FormRequest
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
            'subsidiary_id' => 'required|uuid|exists:subsidiaries,id',
            'nom'           => 'required|string|max:255',
            'description'   => 'nullable|string',
            'date_debut'    => 'required|date',
            'date_fin'      => 'required|date|after_or_equal:date_debut',
            'statut'        => 'in:brouillon,soumis,valide,en_cours,termine,rejete',
            'local_uuid'    => 'nullable|uuid',
        ];
    }
}

