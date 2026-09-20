<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityRequest extends FormRequest
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
            'date_activite' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string',
            'statut' => 'nullable|string|in:planifiee,en_cours,realisee,manquee,annulee,brouillon,soumis_definitif,en_attente,en attente de validation,soumis',
            'heure_debut' => 'nullable|string',
            'heure_fin' => 'nullable|string',
            'gps_debut_lat'  => 'nullable|numeric|between:-90,90',
            'gps_debut_lng'  => 'nullable|numeric|between:-180,180',
            'gps_fin_lat'    => 'nullable|numeric|between:-90,90',
            'gps_fin_lng'    => 'nullable|numeric|between:-180,180',
        ];
    }
}

