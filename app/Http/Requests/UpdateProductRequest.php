<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : ($product ?: $this->route('id'));

        return [
            'product_type_id' => 'nullable|uuid|exists:product_types,id',
            'nom'             => 'sometimes|required|string|max:255',
            'code'            => 'nullable|string|max:100|unique:products,code,' . $productId,
            'marque'          => 'nullable|string|max:150',
            'gamme'           => 'nullable|string|max:150',
            'countries'       => 'nullable|array',
            'countries.*.country_id' => 'required|uuid|exists:countries,id',
            'countries.*.pght' => 'nullable|numeric|min:0',
            'countries.*.prix_cession' => 'nullable|numeric|min:0',
            'countries.*.ppv' => 'nullable|numeric|min:0',
            'countries.*.devise' => 'nullable|string|max:10',
            'countries.*.stock_actuel' => 'nullable|integer|min:0',
            'countries.*.stock_alerte' => 'nullable|integer|min:0',
            'stock_actuel'    => 'nullable|integer|min:0',
            'stock_alerte'    => 'nullable|integer|min:0',
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
