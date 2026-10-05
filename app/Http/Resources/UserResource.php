<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status ? 'active' : 'inactive',
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'manager_id' => $this->manager_id,
            'manager' => new UserResource($this->whenLoaded('manager')),
            'country_id' => $this->country_id,
            'country' => $this->whenLoaded('country'),
            'last_login_at' => $this->last_login_at,
            'otp_enabled' => (bool) $this->otp_enabled,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'contacts_count' => $this->whenCounted('contacts'),
            'accounts_count' => $this->whenCounted('accounts'),
            'activities_count' => $this->whenCounted('activities'),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'accounts' => AccountResource::collection($this->whenLoaded('accounts')),
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
