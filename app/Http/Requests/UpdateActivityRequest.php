<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $rawType = $this->activity_type;
        $typeId = $this->activity_type_id;

        $merge = [
            'gps_debut_lat' => $this->input('gps_debut_lat', $this->input('gps_start_lat')),
            'gps_debut_lng' => $this->input('gps_debut_lng', $this->input('gps_start_lng')),
        ];

        if ($typeId || $rawType) {
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
            if ($activityTypeRecord) {
                $merge['activity_type_id'] = $activityTypeRecord->id;
                $merge['activity_type'] = $activityTypeRecord->name ?? $activityTypeRecord->code;
            } elseif ($rawType) {
                $merge['activity_type'] = $rawType;
            }
        }

        $this->merge($merge);
    }

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_activite'  => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_activite',
            'notes'          => 'nullable|string',
            'statut'         => 'nullable|string|in:planifiee,en_cours,terminee,cloturee,realisee,manquee,annulee,brouillon,soumis_definitif,en_attente,en attente de validation,soumis,validee,valide',
            'report_content' => 'nullable|string',
            'report_status'  => 'nullable|string|in:draft,submitted,validated,rejected',
            'prochain_rdv'   => 'nullable|string',
            'heure_debut'    => 'nullable|string',
            'heure_fin'      => 'nullable|string',
            'duree_minutes'  => 'nullable|integer',
            'gps_debut_lat'  => 'nullable|numeric|between:-90,90',
            'gps_debut_lng'  => 'nullable|numeric|between:-180,180',
            'gps_fin_lat'    => 'nullable|numeric|between:-90,90',
            'gps_fin_lng'    => 'nullable|numeric|between:-180,180',
            'start_time'     => 'nullable|date',
            'end_time'       => 'nullable|date',
            // Produits présentés pendant la visite (intérêt du praticien : faible / moyen / fort)
            'products'                     => 'sometimes|array',
            'products.*.product_id'        => 'required|uuid|exists:products,id',
            'products.*.quantity'          => 'nullable|integer|min:0',
            'products.*.customer_interest' => 'nullable|in:faible,moyen,fort',
            'products.*.remarques'         => 'nullable|string',
            'samples' => 'sometimes|array',
            'samples.*.product_id' => 'required|uuid|exists:products,id',
            'samples.*.lot_number' => 'required|string|max:100',
            'samples.*.quantity' => 'required|integer|min:1',
            'signature'      => 'nullable|string',
            'pharmacy_inventory' => 'nullable|array',
            'pharmacy_inventory.*.product_name' => 'nullable|string',
            'pharmacy_inventory.*.quantity_found' => 'nullable|integer|min:0',
            'motif'            => 'nullable|string',
            'contact_id'       => 'nullable|uuid|exists:contacts,id',
            'account_id'       => 'nullable|uuid|exists:accounts,id',
            'activity_type'    => 'nullable|string',
            'activity_type_id' => 'nullable|uuid',
            'user_id'          => 'nullable|uuid|exists:users,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->validatePlanningRange($validator));
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
