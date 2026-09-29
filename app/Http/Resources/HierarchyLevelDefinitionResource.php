<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HierarchyLevelDefinitionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level_name' => $this->level_name,
            'level_code' => $this->level_code,
            'level_order' => $this->level_order,
            'description' => $this->description,
            'is_mandatory' => $this->is_mandatory,
            'allows_multiple_parents' => $this->allows_multiple_parents,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Organization config relationship
            'organization_config' => new OrganizationConfigResource($this->whenLoaded('organizationConfig')),
            
            // Computed attributes
            'units_count' => $this->when(
                $request->input('include_counts'),
                fn() => $this->organizationalUnits()->where('is_active', true)->count()
            ),
            'is_leaf_level' => $this->level_order === $this->organizationConfig?->levelDefinitions()?->max('level_order'),
            'is_root_level' => $this->level_order === $this->organizationConfig?->levelDefinitions()?->min('level_order'),
        ];
    }
}