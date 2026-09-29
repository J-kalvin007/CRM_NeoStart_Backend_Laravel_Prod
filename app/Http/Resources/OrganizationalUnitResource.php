<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationalUnitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'level_order' => $this->level_order,
            'level_code' => $this->level_code,
            'is_active' => $this->is_active,
            'display_order' => $this->display_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'level_definition' => new HierarchyLevelDefinitionResource($this->whenLoaded('levelDefinition')),
            'parent' => new OrganizationalUnitResource($this->whenLoaded('parent')),
            'children' => OrganizationalUnitResource::collection($this->whenLoaded('children')),
            'organization_config' => new OrganizationConfigResource($this->whenLoaded('organizationConfig')),
            
            // Computed attributes
            'full_path' => $this->when($this->relationLoaded('parent'), function () {
                return $this->getFullPath();
            }),
            'descendants_count' => $this->when(
                $request->input('include_counts'),
                fn() => $this->getDescendants()->count()
            ),
            'users_count' => $this->when(
                $request->input('include_counts'),
                fn() => $this->userScopes()->where('is_active', true)->count()
            ),
            'has_children' => $this->children_count > 0 ?? $this->children->count() > 0,
            'can_delete' => $this->children->isEmpty() && $this->userScopes()->where('is_active', true)->count() === 0,
        ];
    }
}