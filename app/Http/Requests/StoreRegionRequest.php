<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subsidiary_id' => 'required|exists:subsidiaries,id',
            'name' => 'required|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
        ];
    }
}
