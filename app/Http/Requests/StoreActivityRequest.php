<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $rawType = strtolower($this->activity_type ?? 'visite');
        // Conserver le type original pour l'affichage
        $type = $rawType;

        // Mapping interne si nécessaire
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

        if ($this->has('statut')) {
            $st = strtolower(trim((string)$this->statut));
            if (in_array($st, ['en attente de validation', 'en_attente_de_validation', 'soumis', 'submitted', 'planifie'])) {
                $merge['statut'] = 'planifiee';
            } elseif (in_array($st, ['realise', 'réalisée', 'effectuée', 'effectuee'])) {
                $merge['statut'] = 'realisee';
            }
            // Ne pas transformer 'brouillon' en 'planifiee'
        }

        if ($this->has('contact_id') && (!\Illuminate\Support\Str::isUuid((string)$this->contact_id) || !\App\Models\Contact::where('id', (string)$this->contact_id)->exists())) {
            $merge['contact_id'] = null;
        }
        if ($this->has('account_id') && (!\Illuminate\Support\Str::isUuid((string)$this->account_id) || !\App\Models\Account::where('id', (string)$this->account_id)->exists())) {
            $merge['account_id'] = null;
        }

        // Assurer que chaque element de products a un product_id valide
        if ($this->has('products') && is_array($this->products)) {
            $cleanedProducts = [];
            foreach ($this->products as $p) {
                if (!is_array($p)) continue;
                $pId = $p['product_id'] ?? $p['id'] ?? null;
                $pName = $p['nom'] ?? $p['name'] ?? null;

                if (!$pId || !\Illuminate\Support\Str::isUuid((string)$pId)) {
                    if ($pName) {
                        $match = \App\Models\Product::where('nom', $pName)->orWhere('name', $pName)->first();
                        if ($match) $pId = $match->id;
                    }
                }

                if ($pId && \Illuminate\Support\Str::isUuid((string)$pId) && \App\Models\Product::where('id', (string)$pId)->exists()) {
                    $cleanedProducts[] = array_merge($p, ['product_id' => (string)$pId]);
                }
            }

            if (empty($cleanedProducts)) {
                $this->request->remove('products');
            } else {
                $merge['products'] = $cleanedProducts;
            }
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $isDraft = $this->statut === 'brouillon';

        return [
            'tour_plan_id'   => 'nullable|uuid|exists:tour_plans,id',
            'contact_id'     => 'nullable|string|exists:contacts,id',
            'account_id'     => 'nullable|string|exists:accounts,id',
            'activity_type'  => 'required|string',
            'date_activite'  => 'required|date',
            'heure_debut'    => 'required|string',
            'heure_fin'      => 'nullable|string',
            'duree_minutes'  => 'nullable|integer',
            'motif'          => 'nullable|string',
            'notes'          => 'nullable|string',
            'niveau_interet' => 'nullable|integer',
            'statut'         => 'in:planifiee,validee,refusee,en_cours,realisee,manquee,annulee,brouillon,soumis_definitif,en_attente,en attente de validation,soumis',
            'local_uuid'     => 'nullable|string',
            'signature_base64'=> 'nullable|string',
            'user_id'        => 'nullable|string|exists:users,id',

            // Samples
            'samples'              => 'nullable|array',
            'samples.*.product_id' => 'required_with:samples|string|exists:products,id',
            'samples.*.quantite'   => 'required_with:samples|integer|min:1',
            'samples.*.num_lot'    => 'nullable|string',
            'samples.*.date_peremption' => 'nullable|date',

            // Products
            'products'              => 'nullable|array',
            'products.*.product_id' => 'required_with:products|string|exists:products,id',
            'products.*.quantity'   => 'nullable|integer',
            'products.*.customer_interest' => 'nullable|string',
            'products.*.remarques'  => 'nullable|string',

            // Meta (pour group_contacts, centre, objectif, etc.)
            'meta' => 'nullable|array',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('contact_id')) {
                $contact = \App\Models\Contact::withoutGlobalScopes()->find($this->contact_id);
                if ($contact) {
                    $valStatus = strtolower($contact->statut_validation ?? '');
                    $mainStatus = strtolower($contact->statut ?? '');
                    $isValid = in_array($valStatus, ['valide', 'validated', 'approuve']) || in_array($mainStatus, ['valide', 'actif']);
                    if (!$isValid) {
                        $validator->errors()->add('contact_id', 'Le prescripteur sélectionné n\'est pas encore validé par la hiérarchie.');
                    }
                }
            }

            if ($this->filled('account_id')) {
                $account = \App\Models\Account::withoutGlobalScopes()->find($this->account_id);
                if ($account) {
                    $valStatus = strtolower($account->statut_validation ?? '');
                    $mainStatus = strtolower($account->statut ?? '');
                    $isValid = in_array($valStatus, ['valide', 'validated', 'approuve']) || in_array($mainStatus, ['valide', 'actif']);
                    if (!$isValid) {
                        $validator->errors()->add('account_id', 'L\'établissement sélectionné n\'est pas encore validé par la hiérarchie.');
                    }
                }
            }
        });
    }
}
