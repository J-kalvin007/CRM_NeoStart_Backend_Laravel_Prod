<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductTypeRequest extends FormRequest
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
            'code'=>'sometimes|string|max:50','libelle'=>'sometimes|string|max:150','description'=>'nullable|string','is_stockable'=>'boolean','is_distributable'=>'boolean','is_active'=>'boolean','ordre'=>'integer|min:0'
        ];
    }
}

