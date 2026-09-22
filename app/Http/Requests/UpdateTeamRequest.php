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
            'sector_id' => 'sometimes|required|exists:sectors,id',
            'name' => 'sometimes|required|string|max:255',
            'manager_id' => 'nullable|sometimes|exists:users,id',
        ];
    }
}
