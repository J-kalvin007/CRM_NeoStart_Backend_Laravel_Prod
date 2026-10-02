<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OrganizationalUnit extends Model
{
    use HasFactory, HasUuids, HasActivityLogs;

    protected $fillable = [
        'name',
        'type',
        'parent_id',
        'manager_id',
        'metadata',
        'organization_config_id',
        'level_definition_id',
        'level_order',
        'level_code',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Relations hiérarchiques
     */
    public function parent()
    {
        return $this->belongsTo(OrganizationalUnit::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(OrganizationalUnit::class, 'parent_id')
                    ->where('is_active', true)
                    ->orderBy('display_order')
                    ->orderBy('name');
    }

    public function allChildren()
    {
        return $this->hasMany(OrganizationalUnit::class, 'parent_id');
    }

    /**
     * Relations avec les nouveaux modèles
     */
    public function organizationConfig()
    {
        return $this->belongsTo(OrganizationConfig::class);
    }

    public function levelDefinition()
    {
        return $this->belongsTo(HierarchyLevelDefinition::class);
    }

    /**
     * Relations utilisateurs
     */
    public function userScopes()
    {
        return $this->hasMany(UserScope::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_scopes')
                    ->wherePivot('is_active', true)
                    ->withPivot(['is_primary', 'permissions', 'expires_at', 'is_active'])
                    ->withTimestamps();
    }

    public function primaryUsers()
    {
        return $this->belongsToMany(User::class, 'user_scopes')
                    ->wherePivot('is_primary', true)
                    ->wherePivot('is_active', true);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByConfig($query, $configId)
    {
        return $query->where('organization_config_id', $configId);
    }

    public function scopeByLevel($query, string $levelCode)
    {
        return $query->where('level_code', $levelCode);
    }

    public function scopeByOrder($query, int $order)
    {
        return $query->where('level_order', $order);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeWithLevel($query)
    {
        return $query->with('levelDefinition');
    }

    public function scopeWithinUserPerimeter($query, $user = null)
    {
        $user = $user ?? Auth::user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return $query;
        }

        $perimeterService = app(\App\Services\PerimeterService::class);
        $accessibleUnitIds = $perimeterService->getUserAccessibleUnits($user)->pluck('id')->toArray();

        return $query->whereIn('id', $accessibleUnitIds);
    }

    /**
     * Méthodes Helper
     */
    public function isTopLevel(): bool
    {
        return is_null($this->parent_id);
    }

    public function isBottomLevel(): bool
    {
        return $this->children()->count() === 0;
    }

    public function getFullPath(): string
    {
        $path = [];
        $current = $this;

        while ($current) {
            array_unshift($path, $current->name);
            $current = $current->parent;
        }

        return implode(' → ', $path);
    }

    public function getAncestors()
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $ancestors->reverse();
    }

    public function getDescendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }

        return $descendants;
    }

    public function getAllDescendantIds(): array
    {
        return $this->getDescendants()->pluck('id')->toArray();
    }

    public function getLevelName(): string
    {
        return $this->levelDefinition?->level_name ?? $this->type ?? 'Inconnu';
    }

    public function getLevelIcon(): string
    {
        return $this->levelDefinition?->level_icon ?? 'circle';
    }

    public function getLevelColor(): string
    {
        return $this->levelDefinition?->level_color ?? '#0066FF';
    }

    public function canHaveUsers(): bool
    {
        return $this->levelDefinition?->can_have_users ?? true;
    }

    public function canHaveManagers(): bool
    {
        return $this->levelDefinition?->can_have_managers ?? true;
    }

    public function hasActiveUsers(): bool
    {
        return $this->users()->count() > 0;
    }

    public function getUsersCount(): int
    {
        return $this->users()->count();
    }

    public function getChildrenCount(): int
    {
        return $this->children()->count();
    }

    public function getTotalDescendantsCount(): int
    {
        return $this->getDescendants()->count();
    }

    public function isParentOf(OrganizationalUnit $unit): bool
    {
        return $unit->parent_id === $this->id;
    }

    public function isChildOf(OrganizationalUnit $unit): bool
    {
        return $this->parent_id === $unit->id;
    }

    public function isDescendantOf(OrganizationalUnit $unit): bool
    {
        $current = $this->parent;
        
        while ($current) {
            if ($current->id === $unit->id) {
                return true;
            }
            $current = $current->parent;
        }
        
        return false;
    }

    public function isAncestorOf(OrganizationalUnit $unit): bool
    {
        return $unit->isDescendantOf($this);
    }

    public function getDepth(): int
    {
        $depth = 0;
        $current = $this->parent;

        while ($current) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }

    public function canBeDeletedSafely(): bool
    {
        return !$this->hasActiveUsers() && $this->children()->count() === 0;
    }

    public function getNextSibling(): ?self
    {
        if (!$this->parent_id) {
            return null;
        }

        return $this->parent->children()
            ->where('display_order', '>', $this->display_order)
            ->orWhere(function($query) {
                $query->where('display_order', $this->display_order)
                      ->where('name', '>', $this->name);
            })
            ->first();
    }

    public function getPreviousSibling(): ?self
    {
        if (!$this->parent_id) {
            return null;
        }

        return $this->parent->children()
            ->where('display_order', '<', $this->display_order)
            ->orWhere(function($query) {
                $query->where('display_order', $this->display_order)
                      ->where('name', '<', $this->name);
            })
            ->orderBy('display_order', 'desc')
            ->orderBy('name', 'desc')
            ->first();
    }

    public function getSiblings()
    {
        if (!$this->parent_id) {
            return collect();
        }

        return $this->parent->children()->where('id', '!=', $this->id);
    }
}
