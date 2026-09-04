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
            'sector_id' => 'required|exists:sectors,id',
            'name' => 'required|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
        ];
    }
}
