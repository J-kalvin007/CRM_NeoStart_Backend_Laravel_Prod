<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'territory_id' => 'required|exists:territories,id',
            'name' => 'required|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
        ];
    }
}
