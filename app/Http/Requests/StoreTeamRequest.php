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
            'name' => 'required|string|max:255',
            'sector_id' => 'nullable|exists:sectors,id',
            'manager_id' => 'nullable|exists:users,id',
        ];
    }
}
