<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HierarchyLevelDefinition extends Model
{
    use HasFactory, HasUuids, HasActivityLogs;

    protected $fillable = [
        'organization_config_id',
        'level_order',
        'level_name',
        'level_code',
        'level_description',
        'level_icon',
        'level_color',
        'can_have_users',
        'can_have_managers',
        'is_active',
        'is_required',
        'display_settings',
        'validation_rules',
        'metadata',
    ];

    protected $casts = [
        'can_have_users'   => 'boolean',
        'can_have_managers' => 'boolean',
        'is_active'        => 'boolean',
        'is_required'      => 'boolean',
        'display_settings' => 'array',
        'validation_rules' => 'array',
        'metadata'         => 'array',
    ];

    /**
     * Relations
     */
    public function organizationConfig()
    {
        return $this->belongsTo(OrganizationConfig::class);
    }

    public function organizationalUnits()
    {
        return $this->hasMany(OrganizationalUnit::class, 'level_definition_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByOrder($query, int $order)
    {
        return $query->where('level_order', $order);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('level_code', $code);
    }

    public function scopeCanHaveUsers($query)
    {
        return $query->where('can_have_users', true);
    }

    public function scopeCanHaveManagers($query)
    {
        return $query->where('can_have_managers', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Méthodes Helper
     */
    public function isTopLevel(): bool
    {
        return $this->level_order === 1;
    }

    public function isBottomLevel(): bool
    {
        $maxLevel = $this->organizationConfig->getMaxLevel();
        return $this->level_order === $maxLevel;
    }

    public function getParentLevel(): ?self
    {
        if ($this->isTopLevel()) {
            return null;
        }

        return $this->organizationConfig
            ->levelDefinitions()
            ->where('level_order', $this->level_order - 1)
            ->first();
    }

    public function getChildLevel(): ?self
    {
        if ($this->isBottomLevel()) {
            return null;
        }

        return $this->organizationConfig
            ->levelDefinitions()
            ->where('level_order', $this->level_order + 1)
            ->first();
    }

    public function getDisplayName(): string
    {
        return $this->level_name;
    }

    public function getIconClass(): string
    {
        return $this->level_icon ?? 'circle';
    }

    public function getColorCode(): string
    {
        return $this->level_color ?? '#0066FF';
    }

    public function validateName(string $name): bool
    {
        $rules = $this->validation_rules;
        
        if (!$rules) {
            return true;
        }

        // Vérification longueur minimale
        if (isset($rules['name_min_length']) && strlen($name) < $rules['name_min_length']) {
            return false;
        }

        // Vérification longueur maximale
        if (isset($rules['name_max_length']) && strlen($name) > $rules['name_max_length']) {
            return false;
        }

        return true;
    }
}