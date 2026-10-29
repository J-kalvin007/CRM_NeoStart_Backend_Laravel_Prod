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
        // route('user') peut être un modèle Eloquent (route model binding)
        // ou une simple string (UUID dans l'URL)
        $routeUser = $this->route('user');
        if (is_object($routeUser) && isset($routeUser->id)) {
            $userId = $routeUser->id;
        } else {
            // C'est une string UUID ou l'id est dans 'id'
            $userId = is_string($routeUser) ? $routeUser : $this->route('id');
        }

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,'.$userId],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'in:active,inactive,true,false,1,0'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'field_post_id' => ['nullable', 'uuid', 'exists:field_posts,id'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'otp_enabled' => ['nullable', 'boolean'],
            'manager_id' => ['nullable', 'uuid', 'exists:users,id'],
            'password' => ['nullable', 'string', 'min:6'],
        ];
    }
}
