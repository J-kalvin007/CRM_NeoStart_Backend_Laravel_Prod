<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationConfig extends Model
{
    use HasFactory, HasUuids, HasActivityLogs;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'max_hierarchy_levels',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Relations
     */
    public function levelDefinitions()
    {
        return $this->hasMany(HierarchyLevelDefinition::class)->orderBy('level_order');
    }

    /**
     * Alias de levelDefinitions pour la compatibilité avec le service/contrôleur.
     */
    public function hierarchyLevels()
    {
        return $this->hasMany(HierarchyLevelDefinition::class)->orderBy('level_order');
    }

    public function organizationalUnits()
    {
        return $this->hasMany(OrganizationalUnit::class);
    }

    public function rolePermissions()
    {
        return $this->hasMany(RoleHierarchyPermission::class);
    }

    public function userScopes()
    {
        return $this->hasMany(UserScope::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Méthodes Helper
     */
    public function isDefault(): bool
    {
        return $this->code === 'crm_default';
    }

    public function getLevelDefinitionByCode(string $levelCode): ?HierarchyLevelDefinition
    {
        return $this->levelDefinitions()->where('level_code', $levelCode)->first();
    }

    public function getLevelDefinitionByOrder(int $order): ?HierarchyLevelDefinition
    {
        return $this->levelDefinitions()->where('level_order', $order)->first();
    }

    public function getMaxLevel(): int
    {
        return $this->levelDefinitions()->max('level_order') ?? 0;
    }

    public function canHaveMoreLevels(): bool
    {
        return $this->getMaxLevel() < $this->max_hierarchy_levels;
    }

    public function getActiveLevels()
    {
        return $this->levelDefinitions()->where('is_required', true)->orderBy('level_order');
    }
}