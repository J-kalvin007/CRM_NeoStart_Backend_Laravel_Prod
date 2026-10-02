<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserScope extends Model
{
    use HasFactory, HasUuids, HasActivityLogs;

    protected $fillable = [
        'user_id',
        'organizational_unit_id',
        'organization_config_id',
        'is_primary',
        'permissions',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'permissions' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function organizationConfig()
    {
        return $this->belongsTo(OrganizationConfig::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForConfig($query, $configId)
    {
        return $query->where('organization_config_id', $configId);
    }

    public function scopeByLevel($query, string $levelCode)
    {
        return $query->whereHas('organizationalUnit', function($q) use ($levelCode) {
            $q->where('level_code', $levelCode);
        });
    }

    /**
     * Méthodes Helper
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function isPrimaryScope(): bool
    {
        return $this->is_primary;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    public function getPermissions(): array
    {
        return $this->permissions ?? [];
    }

    public function addPermission(string $permission): void
    {
        $permissions = $this->getPermissions();
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    public function removePermission(string $permission): void
    {
        $permissions = $this->getPermissions();
        $permissions = array_filter($permissions, fn($p) => $p !== $permission);
        $this->permissions = array_values($permissions);
        $this->save();
    }

    public function getHierarchyPath(): string
    {
        if (!$this->organizationalUnit) {
            return '';
        }

        $path = [];
        $current = $this->organizationalUnit;

        while ($current) {
            array_unshift($path, $current->name);
            $current = $current->parent;
        }

        return implode(' → ', $path);
    }

    public function getLevelName(): string
    {
        return $this->organizationalUnit?->levelDefinition?->level_name ?? 'Inconnu';
    }

    public function canExpire(): bool
    {
        return !is_null($this->expires_at);
    }

    public function extendExpiration(\DateTime $newExpiration): void
    {
        $this->expires_at = $newExpiration;
        $this->save();
    }

    public function makeTemporary(\DateTime $expiration): void
    {
        $this->expires_at = $expiration;
        $this->save();
    }

    public function makePermanent(): void
    {
        $this->expires_at = null;
        $this->save();
    }
}