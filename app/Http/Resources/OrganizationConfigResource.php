<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationConfigResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'max_hierarchy_depth' => $this->max_hierarchy_depth,
            'allow_cross_level_assignment' => $this->allow_cross_level_assignment,
            'strict_perimeter_isolation' => $this->strict_perimeter_isolation,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'level_definitions' => HierarchyLevelDefinitionResource::collection($this->whenLoaded('levelDefinitions')),
            
            // Computed attributes
            'levels_count' => $this->when(
                $request->input('include_counts'),
                fn() => $this->levelDefinitions()->count()
            ),
            'units_count' => $this->when(
                $request->input('include_counts'),
                fn() => $this->organizationalUnits()->where('is_active', true)->count()
            ),
            'current_depth' => $this->when(
                $this->relationLoaded('levelDefinitions'),
                fn() => $this->levelDefinitions->max('level_order')
            ),
        ];
    }
}