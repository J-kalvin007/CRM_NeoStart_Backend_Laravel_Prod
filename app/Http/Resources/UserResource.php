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
            'scopes' => OrganizationalUnitResource::collection($this->whenLoaded('scopes')),
            'scope_ids' => $this->whenLoaded('scopes', function () {
                return $this->scopes->pluck('id');
            }),
            'last_login_at' => $this->last_login_at,
            'otp_enabled' => (bool) $this->otp_enabled,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
