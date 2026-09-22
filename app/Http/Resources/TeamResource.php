<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sector_id' => $this->sector_id,
            'manager_id' => $this->manager_id,
            'sector' => $this->whenLoaded('sector', fn () => [
                'id' => $this->sector->id,
                'name' => $this->sector->name,
                'code' => $this->sector->code,
            ]),
            'manager' => new UserResource($this->whenLoaded('manager')),
            'members_count' => $this->when(isset($this->members_count), $this->members_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
