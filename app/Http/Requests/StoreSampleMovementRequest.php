<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSampleMovementRequest extends FormRequest
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
            'product_id'    => 'required|uuid|exists:products,id',
            'movement_type' => 'required|in:reception,attribution,distribution,retour,destruction,inventaire',
            'quantity'      => 'required|integer|min:1',
            'activity_id'   => 'nullable|uuid|exists:activities,id',
            'notes'         => 'nullable|string',
        ];
    }
}

