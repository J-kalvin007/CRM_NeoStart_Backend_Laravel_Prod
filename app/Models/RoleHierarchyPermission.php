<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleHierarchyPermission extends Model
{
    use HasFactory, HasUuids, HasActivityLogs;

    protected $fillable = [
        'role_id',
        'organization_config_id',
        'allowed_level_codes',
        'can_manage_level_codes',
        'access_scope',
        'can_create_units',
        'can_edit_units',
        'can_delete_units',
        'can_assign_users',
        'restrictions',
    ];

    protected $casts = [
        'allowed_level_codes' => 'array',
        'can_manage_level_codes' => 'array',
        'can_create_units' => 'boolean',
        'can_edit_units' => 'boolean',
        'can_delete_units' => 'boolean',
        'can_assign_users' => 'boolean',
        'restrictions' => 'array',
    ];

    /**
     * Relations
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function organizationConfig()
    {
        return $this->belongsTo(OrganizationConfig::class);
    }

    /**
     * Scopes
     */
    public function scopeForRole($query, $roleId)
    {
        return $query->where('role_id', $roleId);
    }

    public function scopeForConfig($query, $configId)
    {
        return $query->where('organization_config_id', $configId);
    }

    public function scopeGlobalAccess($query)
    {
        return $query->where('access_scope', 'global');
    }

    public function scopeTeamAccess($query)
    {
        return $query->where('access_scope', 'team_perimeter');
    }

    public function scopeOwnAccess($query)
    {
        return $query->where('access_scope', 'own_perimeter');
    }

    /**
     * Méthodes Helper
     */
    public function canAccessLevel(string $levelCode): bool
    {
        return in_array($levelCode, $this->allowed_level_codes ?? []);
    }

    public function canManageLevel(string $levelCode): bool
    {
        return in_array($levelCode, $this->can_manage_level_codes ?? []);
    }

    public function hasGlobalAccess(): bool
    {
        return $this->access_scope === 'global';
    }

    public function hasTeamAccess(): bool
    {
        return $this->access_scope === 'team_perimeter';
    }

    public function hasOwnAccess(): bool
    {
        return $this->access_scope === 'own_perimeter';
    }

    public function hasRestriction(string $restriction): bool
    {
        return in_array($restriction, $this->restrictions ?? []);
    }

    public function getAllowedLevels(): array
    {
        return $this->allowed_level_codes ?? [];
    }

    public function getManageableLevels(): array
    {
        return $this->can_manage_level_codes ?? [];
    }

    public function canPerformAction(string $action): bool
    {
        return match($action) {
            'create_units' => $this->can_create_units,
            'edit_units' => $this->can_edit_units,
            'delete_units' => $this->can_delete_units,
            'assign_users' => $this->can_assign_users,
            default => false
        };
    }

    public function getAccessScopeLabel(): string
    {
        return match($this->access_scope) {
            'global' => 'Accès Global',
            'team_perimeter' => 'Périmètre Équipe',
            'own_perimeter' => 'Périmètre Personnel',
            default => 'Inconnu'
        };
    }
}