<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user') ? $this->route('user')->id : $this->route('id');
        
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $userId],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'team_id' => ['nullable', 'uuid', 'exists:teams,id'],
            'manager_id' => ['nullable', 'uuid', 'exists:users,id'],
            'country_id' => ['nullable', 'uuid'],
            'subsidiary_id' => ['nullable', 'uuid'],
            'region_id' => ['nullable', 'uuid'],
            'territory_id' => ['nullable', 'uuid'],
            'zone_id' => ['nullable', 'uuid'],
            'sector_id' => ['nullable', 'uuid'],
            'otp_enabled' => ['nullable', 'boolean'],
        ];
    }
}
