<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function prepareForValidation()
    {
        if (!$this->has('subsidiary_id') && $this->user()) {
            $this->merge([
                'subsidiary_id' => $this->user()->subsidiary_id
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'subsidiary_id'   => 'nullable|uuid|exists:subsidiaries,id',
            'organizational_unit_ids' => 'nullable|array',
            'organizational_unit_ids.*' => 'uuid|exists:organizational_units,id',
            'product_type_id' => 'nullable|uuid|exists:product_types,id',
            'nom'             => 'required|string|max:255',
            'code'            => 'nullable|string|max:100|unique:products,code',
            'marque'          => 'nullable|string|max:150',
            'gamme'           => 'nullable|string|max:150',
            'pght'            => 'nullable|numeric|min:0',
            'ppv'             => 'nullable|numeric|min:0',
            'devise'          => 'nullable|string|max:10',
            'stock_actuel'    => 'integer|min:0',
            'stock_alerte'    => 'integer|min:0',
            'description'     => 'nullable|string',
            'image_url'       => 'nullable|url',
            'statut'          => 'in:brouillon,actif,archive',
            'laboratory_id'        => 'nullable|uuid|exists:laboratories,id',
            'therapeutic_class_id' => 'nullable|uuid|exists:therapeutic_classes,id',
            'galenic_form_id'      => 'nullable|uuid|exists:galenic_forms,id',
            'dci'                  => 'nullable|string|max:255',
            'conditionnement'      => 'nullable|string|max:255',
            'indications'          => 'nullable|string',
            'posologie'            => 'nullable|string',
            'effets_secondaires'   => 'nullable|string',
            'meta'            => 'nullable|array',
        ];
    }
}
