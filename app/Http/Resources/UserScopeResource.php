<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserScopeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'organizational_unit_id' => $this->organizational_unit_id,
            'assigned_at' => $this->assigned_at,
            'expires_at' => $this->expires_at,
            'is_active' => $this->is_active,
            'access_type' => $this->access_type,
            'notes' => $this->notes,
            'assigned_by' => $this->assigned_by,
            'revoked_by' => $this->revoked_by,
            'revoked_at' => $this->revoked_at,
            'revocation_reason' => $this->revocation_reason,
            'extended_by' => $this->extended_by,
            'extended_at' => $this->extended_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'organizational_unit' => new OrganizationalUnitResource($this->whenLoaded('organizationalUnit')),
            'assigned_by_user' => new UserResource($this->whenLoaded('assignedBy')),
            'revoked_by_user' => new UserResource($this->whenLoaded('revokedBy')),
            'extended_by_user' => new UserResource($this->whenLoaded('extendedBy')),
            
            // Computed attributes
            'is_expired' => $this->expires_at && $this->expires_at->isPast(),
            'days_until_expiry' => $this->when($this->expires_at, function () {
                return $this->expires_at->diffInDays(now(), false);
            }),
            'is_expiring_soon' => $this->when($this->expires_at, function () {
                return $this->expires_at->isPast() || $this->expires_at->diffInDays(now()) <= 7;
            }),
            'status' => $this->getStatus(),
        ];
    }
    
    /**
     * Get the status of the user scope
     */
    private function getStatus(): string
    {
        if (!$this->is_active) {
            return 'revoked';
        }
        
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }
        
        if ($this->expires_at && $this->expires_at->diffInDays(now()) <= 7) {
            return 'expiring_soon';
        }
        
        return 'active';
    }
}