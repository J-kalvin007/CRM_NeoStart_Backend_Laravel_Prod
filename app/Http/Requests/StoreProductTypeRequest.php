<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductTypeRequest extends FormRequest
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
        return [
            'code'=>'required|string|max:50|unique:product_types,code','libelle'=>'required|string|max:150','description'=>'nullable|string','is_stockable'=>'boolean','is_distributable'=>'boolean','couleur'=>'nullable|string','icone'=>'nullable|string|max:100','is_active'=>'boolean','ordre'=>'integer|min:0'
        ];
    }
}

