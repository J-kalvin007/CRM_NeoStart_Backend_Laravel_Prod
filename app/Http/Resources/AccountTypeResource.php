<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['is_account_only'] = (bool) ($this->is_account_only ?? false);
        $data['activity_types'] = $this->whenLoaded('activityTypes', fn() => $this->activityTypes);
        $data['activity_type_ids'] = $this->whenLoaded('activityTypes', fn() => $this->activityTypes->pluck('id'));
        return $data;
    }
}
