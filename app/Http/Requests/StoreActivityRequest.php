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
            'contact_id'     => 'nullable|string|exists:contacts,id',
            'account_id'     => 'nullable|string|exists:accounts,id',
            'activity_type'  => 'required|string',
            'date_activite'  => 'required|date',
            'heure_debut'    => 'nullable|string',
            'heure_fin'      => 'nullable|string',
            'motif'          => 'nullable|string',
            'notes'          => 'nullable|string',
            'niveau_interet' => 'nullable|integer',
            'statut'         => 'in:planifiee,en_cours,realisee,manquee,annulee,brouillon,soumis_definitif',
            'local_uuid'     => 'nullable|string',
            'signature_base64'=> 'nullable|string',
            
            // Samples
            'samples'              => 'nullable|array',
            'samples.*.product_id' => 'required_with:samples|string|exists:products,id',
            'samples.*.quantite'   => 'required_with:samples|integer|min:1',
            'samples.*.num_lot'    => 'nullable|string',
            'samples.*.date_peremption' => 'nullable|date',
        ];
    }
}
