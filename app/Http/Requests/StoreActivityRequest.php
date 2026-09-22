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

        $user = auth()->user();
        $merge = [
            'activity_type' => $type,
        ];
        if (!$this->has('subsidiary_id') || empty($this->subsidiary_id)) {
            $merge['subsidiary_id'] = $user?->subsidiary_id ?? \App\Models\Subsidiary::first()?->id;
        }

        if ($this->has('statut')) {
            $st = strtolower(trim((string)$this->statut));
            if (in_array($st, ['en attente de validation', 'en_attente_de_validation', 'soumis', 'submitted', 'planifie', 'brouillon'])) {
                $merge['statut'] = 'planifiee';
            } elseif (in_array($st, ['realise', 'réalisée', 'effectuée', 'effectuee'])) {
                $merge['statut'] = 'realisee';
            }
        }

        if ($this->has('contact_id') && !\Illuminate\Support\Str::isUuid((string)$this->contact_id)) {
            $merge['contact_id'] = null;
        }
        if ($this->has('account_id') && !\Illuminate\Support\Str::isUuid((string)$this->account_id)) {
            $merge['account_id'] = null;
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'subsidiary_id'  => 'nullable|uuid',
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
            'statut'         => 'in:planifiee,en_cours,realisee,manquee,annulee,brouillon,soumis_definitif,en_attente,en attente de validation,soumis',
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
