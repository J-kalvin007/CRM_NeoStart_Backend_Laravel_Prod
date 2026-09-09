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
            'status' => $this->status,
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'team' => new TeamResource($this->whenLoaded('team')),
            'team_id' => $this->team_id,
            'manager_id' => $this->manager_id,
            'manager' => new UserResource($this->whenLoaded('manager')),
            'country_id' => $this->country_id,
            'subsidiary_id' => $this->subsidiary_id,
            'region_id' => $this->region_id,
            'territory_id' => $this->territory_id,
            'zone_id' => $this->zone_id,
            'sector_id' => $this->sector_id,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
