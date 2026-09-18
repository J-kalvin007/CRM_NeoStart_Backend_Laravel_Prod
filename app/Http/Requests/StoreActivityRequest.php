<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $rawType = strtolower($this->activity_type ?? 'visite');
        if (str_contains($rawType, 'visite')) {
            $type = 'visite';
        } elseif (str_contains($rawType, 'appel')) {
            $type = 'appel';
        } elseif (str_contains($rawType, 'email')) {
            $type = 'email';
        } elseif (str_contains($rawType, 'reunion') || str_contains($rawType, 'formation')) {
            $type = 'reunion';
        } elseif (str_contains($rawType, 'conference') || str_contains($rawType, 'staff') || str_contains($rawType, 'présentation')) {
            $type = 'conference';
        } else {
            $type = 'visite';
        }

        $this->merge([
            'activity_type' => $type,
        ]);
    }

    public function rules(): array
    {
        return [
            'tour_plan_id'   => 'nullable|uuid|exists:tour_plans,id',
            'contact_id'     => 'nullable|uuid|exists:contacts,id',
            'account_id'     => 'nullable|uuid|exists:accounts,id',
            'activity_type'  => 'required|string',
            'activity_type_id'=> 'nullable|uuid|exists:activity_types,id',
            'date_activite'  => 'required|date',
            'heure_debut'    => 'nullable|string',
            'heure_fin'      => 'nullable|string',
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
