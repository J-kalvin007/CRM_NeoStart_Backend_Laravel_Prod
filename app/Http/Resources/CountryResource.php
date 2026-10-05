<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'iso_code'            => $this->iso_code,
            'currency'            => $this->currency,
            'language'            => $this->language,
            'timezone'            => $this->timezone,
            'regulatory_settings' => $this->regulatory_settings,
            'status'              => (bool) $this->status,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}
