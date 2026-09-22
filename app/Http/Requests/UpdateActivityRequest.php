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
        return [
            'notes'          => 'nullable|string',
            'statut'         => 'nullable|string|in:planifiee,en_cours,realisee,manquee,annulee,validee,valide',
            'report_content' => 'nullable|string',
            'report_status'  => 'nullable|string|in:draft,submitted,validated,rejected',
            'prochain_rdv'   => 'nullable|string',
            'heure_debut'    => 'nullable|string',
            'heure_fin'      => 'nullable|string',
            'duree_minutes'  => 'nullable|integer',
            'gps_fin_lat'    => 'nullable|numeric',
            'gps_fin_lng'    => 'nullable|numeric',
            'signature'      => 'nullable|string',
        ];
}

