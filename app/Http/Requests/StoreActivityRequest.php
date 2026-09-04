<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tour_plan_id'   => 'nullable|uuid|exists:tour_plans,id',
            'contact_id'     => 'nullable|uuid|exists:contacts,id',
            'account_id'     => 'nullable|uuid|exists:accounts,id',
            'activity_type'  => 'required|in:visite,appel,email,reunion,conference',
            'date_activite'  => 'required|date',
            'heure_debut'    => 'nullable|date_format:H:i',
            'heure_fin'      => 'nullable|date_format:H:i|after:heure_debut',
            'gps_debut_lat'  => 'nullable|numeric|between:-90,90',
            'gps_debut_lng'  => 'nullable|numeric|between:-180,180',
            'gps_fin_lat'    => 'nullable|numeric|between:-90,90',
            'gps_fin_lng'    => 'nullable|numeric|between:-180,180',
            'notes'          => 'nullable|string',
            'prochain_rdv'   => 'nullable|string',
            'statut'         => 'in:planifiee,en_cours,realisee,manquee,annulee',
            'local_uuid'     => 'nullable|uuid|unique:activities,local_uuid',
            // Produits distribués (optionnel)
            'products'              => 'nullable|array',
            'products.*.product_id' => 'required_with:products|uuid|exists:products,id',
            'products.*.quantity'   => 'integer|min:0',
            'products.*.customer_interest' => 'nullable|in:faible,moyen,fort',
            'products.*.remarques'  => 'nullable|string',
        ];
    }
}
