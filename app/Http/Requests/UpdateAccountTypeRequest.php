<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountTypeRequest extends FormRequest
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
        $id = $this->route('id') ?? $this->route('account_type');

        return [
            'code' => 'sometimes|string|max:50|unique:account_types,code,' . $id,
            'libelle' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'couleur' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'icone' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'ordre' => 'integer|min:0',
            'activity_type_ids' => 'nullable|array',
            'activity_type_ids.*' => 'string|exists:activity_types,id',
        ];
    }
}

