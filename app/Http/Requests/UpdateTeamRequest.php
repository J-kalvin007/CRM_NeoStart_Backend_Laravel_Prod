<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'sector_id' => 'nullable|sometimes|exists:sectors,id',
            'manager_id' => 'nullable|sometimes|exists:users,id',
        ];
    }
}
