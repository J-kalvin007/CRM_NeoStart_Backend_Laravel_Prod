<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SampleMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'product_id'    => $this->product_id,
            'product'       => $this->whenLoaded('product', function () {
                return [
                    'id'           => $this->product->id,
                    'nom'          => $this->product->nom,
                    'code'         => $this->product->code,
                    'marque'       => $this->product->marque,
                    'stock_actuel' => $this->product->stock_actuel,
                    'stock_alerte' => $this->product->stock_alerte,
                ];
            }),
            'user_id'       => $this->user_id,
            'user'          => $this->whenLoaded('user', function () {
                return [
                    'id'    => $this->user->id,
                    'name'  => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'activity_id'   => $this->activity_id,
            'movement_type' => $this->movement_type,
            'quantity'      => $this->quantity,
            'lot_number'    => $this->lot_number,
            'expiry_date'   => $this->expiry_date ? $this->expiry_date->format('Y-m-d') : null,
            'signature_url' => $this->signature_url,
            'qr_code'       => $this->qr_code,
            'stock_apres'   => $this->stock_apres,
            'remarques'     => $this->remarques,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
