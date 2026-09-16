<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'subsidiary_id'  => 'required|uuid|exists:subsidiaries,id',
            'tour_plan_id'   => 'nullable|uuid|exists:tour_plans,id',
            'contact_id'     => 'nullable|uuid|exists:contacts,id',
            'account_id'     => 'nullable|uuid|exists:accounts,id',
            'activity_type_id'=> 'required|uuid|exists:activity_types,id',
            'date_activite'  => 'required|date|after_or_equal:today',
            'heure_debut'    => 'nullable|date_format:H:i',
            'heure_fin'      => 'nullable|date_format:H:i|after:heure_debut',
            'duration'       => 'nullable|integer|min:0',
            'gps_debut_lat'  => 'nullable|numeric|between:-90,90',
            'gps_debut_lng'  => 'nullable|numeric|between:-180,180',
            'gps_fin_lat'    => 'nullable|numeric|between:-90,90',
            'gps_fin_lng'    => 'nullable|numeric|between:-180,180',
            'motif'          => 'nullable|string',
            'notes'          => 'nullable|string',
            'prochain_rdv'   => 'nullable|date',
            'statut'         => 'in:planifiee,en_cours,realisee,manquee,annulee',
            'local_uuid'     => 'nullable|uuid|unique:activities,local_uuid',
            
            // Produits présentés/distribués
            'products'              => 'nullable|array',
            'products.*.product_id' => 'required_with:products|uuid|exists:products,id',
            'products.*.quantity'   => 'integer|min:0',
            'products.*.customer_interest' => 'nullable|string',
            'products.*.reaction'   => 'nullable|string',
            'products.*.remarques'  => 'nullable|string',

            // Documents / e-detailing
            'documents'              => 'nullable|array',
            'documents.*.document_id'=> 'required_with:documents|uuid|exists:documents,id',
            'documents.*.duration_seconds' => 'nullable|integer',
            'documents.*.display_order' => 'nullable|integer',
        ];
    }
}
