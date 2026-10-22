<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $rawType = $this->activity_type;
        $typeId = $this->activity_type_id;

        $activityTypeRecord = null;
        if ($typeId && \Illuminate\Support\Str::isUuid((string)$typeId)) {
            $activityTypeRecord = \App\Models\ActivityType::find($typeId);
        }
        if (!$activityTypeRecord && $rawType) {
            $rawLower = strtolower(trim((string)$rawType));
            if (in_array($rawLower, ['visite individuelle', 'visite_individuelle', 'one to one', 'one_to_one', 'one-to-one'])) {
                $activityTypeRecord = \App\Models\ActivityType::where('code', 'ONE-TO-ONE')->orWhere('code', 'one-to-one')->first();
            } elseif (in_array($rawLower, ['staff hospitalier', 'staff_hospitalier', 'staff'])) {
                $activityTypeRecord = \App\Models\ActivityType::where('code', 'staff')->first();
            } elseif (in_array($rawLower, ['négociation de commande', 'negociation de commande', 'negociation'])) {
                $activityTypeRecord = \App\Models\ActivityType::where('code', 'negociation')->first();
            } elseif (in_array($rawLower, ['relation publique', 'relation_publique', 'rp'])) {
                $activityTypeRecord = \App\Models\ActivityType::where('code', 'rp')->first();
            } elseif (in_array($rawLower, ['table ronde', 'table_ronde'])) {
                $activityTypeRecord = \App\Models\ActivityType::where('code', 'TABLE_RONDE')->orWhere('code', 'table_ronde')->first();
            }
            if (!$activityTypeRecord) {
                $activityTypeRecord = \App\Models\ActivityType::where('id', (string)$rawType)
                    ->orWhere('code', strtolower((string)$rawType))
                    ->orWhere('name', (string)$rawType)
                    ->first();
            }
        }

        $merge = [];
        if ($activityTypeRecord) {
            $merge['activity_type_id'] = $activityTypeRecord->id;
            $merge['activity_type'] = $activityTypeRecord->name ?? $activityTypeRecord->code;
        } elseif ($rawType) {
            $merge['activity_type'] = $rawType;
        } else {
            $merge['activity_type'] = 'visite';
        }

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
            'activity_type_id' => 'nullable|uuid|exists:activity_types,id',
            'date_activite'  => 'required|date',
            'date_fin' => 'nullable|date|after_or_equal:date_activite',
            'outside_weekly_plan' => 'sometimes|boolean',
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
            $this->validatePlanningRange($validator);
            $selectedType = $this->activity_type_id ? \App\Models\ActivityType::find($this->activity_type_id) : null;
            if ($selectedType && !$selectedType->is_active) $validator->errors()->add('activity_type', 'Ce type d’activité est désactivé.');
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

    private function validatePlanningRange($validator): void
    {
        $existing = null;
        $id = $this->route('activity') ?? $this->route('id');
        if ($id) $existing = \App\Models\Activity::withoutGlobalScopes()->find($id);
        $typeId = $this->input('activity_type_id', $existing?->activity_type_id);
        $record = $typeId ? \App\Models\ActivityType::find($typeId) : null;
        $type = \App\Services\PlanningOptions::kind($record?->code ?? $this->input('activity_type', $existing?->activity_type ?? ''));
        if ($type === 'visite') return;
        if ($record && !$record->is_active) $validator->errors()->add('activity_type', 'Ce type d’activité est désactivé.');
        $startDate = $this->input('date_activite', $existing?->date_activite?->toDateString());
        $endDate = $this->input('date_fin', $existing?->date_fin?->toDateString());
        $startTime = $this->input('heure_debut', $existing?->heure_debut);
        $endTime = $this->input('heure_fin', $existing?->heure_fin);
        if (!$endDate || !$endTime) {
            $validator->errors()->add('date_fin', 'La date et l’heure de fin sont obligatoires pour cette activité.');
            return;
        }
        try {
            if (\Illuminate\Support\Carbon::parse($endDate . ' ' . $endTime)->lte(\Illuminate\Support\Carbon::parse($startDate . ' ' . $startTime))) {
                $validator->errors()->add('date_fin', 'La fin doit être postérieure au début.');
            }
        } catch (\Throwable $e) {
            $validator->errors()->add('date_fin', 'La période de planification est invalide.');
        }
        if ($this->boolean('outside_weekly_plan')) {
            $validator->errors()->add('outside_weekly_plan', 'La marge hors planning est réservée aux visites.');
        }
    }
}
