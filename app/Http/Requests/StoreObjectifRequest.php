<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreObjectifRequest extends FormRequest
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
    /**
     * L'application envoie le code du type (type_objectif / type) : on le résout en objectif_type_id.
     */
    protected function prepareForValidation(): void
    {
        $typeInput = $this->input('objectif_type_id') ?? $this->input('type_objectif') ?? $this->input('type');
        if ($typeInput) {
            if (\Illuminate\Support\Str::isUuid((string)$typeInput)) {
                if (\Illuminate\Support\Facades\DB::table('objectif_types')->where('id', $typeInput)->exists()) {
                    $this->merge(['objectif_type_id' => $typeInput]);
                }
            } else {
                $id = \Illuminate\Support\Facades\DB::table('objectif_types')->where('code', $typeInput)->value('id');
                if (!$id) {
                    $id = \Illuminate\Support\Facades\DB::table('objectif_types')->where('name', 'like', "%{$typeInput}%")->value('id');
                }
                if ($id) {
                    $this->merge(['objectif_type_id' => $id]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => 'nullable|exists:users,id',
            'country_id' => 'nullable|uuid|exists:countries,id',
            'indicator_id' => 'nullable|uuid|exists:indicators,id',
            'objectif_type_id' => 'required|uuid|exists:objectif_types,id',
            'assigned_by_user_id' => 'nullable|exists:users,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'periode' => 'required|string',
            'valeur_cible' => 'required|numeric|gt:0',
            'valeur_realisee' => 'nullable|numeric',
            'taux_realisation' => 'nullable|numeric',
            'product_id' => 'nullable|uuid|exists:products,id',
            'description' => 'nullable|string',
            'statut' => 'nullable|string|in:en_cours,actif,atteint,depasse,non_atteint,annule',
        ];
    }
}

