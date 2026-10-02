<?php

namespace App\Services;

use App\Models\OrganizationConfig;
use App\Models\HierarchyLevelDefinition;
use App\Models\OrganizationalUnit;
use App\Models\UserScope;
use App\Models\RoleHierarchyPermission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HierarchyConfigService
{
    const CACHE_PREFIX = 'hierarchy_config';
    const CACHE_TTL = 3600;

    protected function resolveConfigCode(?string $requestedCode, ?string $name): string
    {
        $base = trim((string) ($requestedCode ?? $name ?? 'organization'));
        $base = preg_replace('/[^a-z0-9]+/i', '_', $base);
        $base = trim((string) $base, '_');
        $base = strtolower($base !== '' ? $base : 'organization');

        $candidate = $base;
        $suffix = 1;

        while (OrganizationConfig::where('code', $candidate)->exists()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    // ── Organization Configs ──────────────────────────────────────────────────

    /**
     * Create a new organization configuration with its hierarchy levels.
     */
    public function createOrganizationConfig(array $data): OrganizationConfig
    {
        return DB::transaction(function () use ($data) {
            $config = OrganizationConfig::create([
                'name'        => $data['name'],
                'code'        => $this->resolveConfigCode($data['code'] ?? null, $data['name'] ?? null),
                'description' => $data['description'] ?? null,
                'is_active'   => $data['is_active'] ?? true,
                'settings'    => $data['settings'] ?? [],
            ]);

            if (!empty($data['levels'])) {
                $this->createHierarchyLevels($config, $data['levels']);
            }

            $this->clearCache();

            return $config->load('hierarchyLevels');
        });
    }

    /**
     * Update an existing organization configuration.
     */
    public function updateOrganizationConfig(OrganizationConfig $config, array $data): OrganizationConfig
    {
        return DB::transaction(function () use ($config, $data) {
            $config->update([
                'name'        => $data['name']        ?? $config->name,
                'description' => $data['description'] ?? $config->description,
                'is_active'   => $data['is_active']   ?? $config->is_active,
                'settings'    => array_merge($config->settings ?? [], $data['settings'] ?? []),
            ]);

            if (isset($data['levels'])) {
                $this->updateHierarchyLevels($config, $data['levels']);
            }

            $this->clearCache();

            return $config->load('hierarchyLevels');
        });
    }

    /**
     * Deactivate an organization configuration (soft delete).
     */
    public function deleteOrganizationConfig(OrganizationConfig $config): bool
    {
        if ($this->isConfigurationInUse($config)) {
            throw new \Exception('Cannot delete an organization configuration that has active organizational units.');
        }

        return DB::transaction(function () use ($config) {
            $config->hierarchyLevels()->update(['is_active' => false]);
            $config->update(['is_active' => false]);
            $this->clearCache();
            return true;
        });
    }

    /**
     * Clone a configuration with all its hierarchy levels.
     */
    public function cloneConfiguration(OrganizationConfig $source, string $newName, ?string $newDescription = null): OrganizationConfig
    {
        return DB::transaction(function () use ($source, $newName, $newDescription) {
            $newConfig = OrganizationConfig::create([
                'name'        => $newName,
                'code'        => $this->resolveConfigCode(null, $newName),
                'description' => $newDescription ?? "Copie de {$source->name}",
                'is_active'   => true,
                'settings'    => $source->settings ?? [],
            ]);

            foreach ($source->hierarchyLevels as $level) {
                HierarchyLevelDefinition::create([
                    'organization_config_id' => $newConfig->id,
                    'level_order'            => $level->level_order,
                    'level_code'             => $level->level_code,
                    'level_name'             => $level->level_name,
                    'level_description'      => $level->level_description,
                    'level_icon'             => $level->level_icon,
                    'level_color'            => $level->level_color,
                    'can_have_users'         => $level->can_have_users,
                    'can_have_managers'      => $level->can_have_managers,
                    'is_active'              => $level->is_active,
                    'metadata'               => $level->metadata ?? [],
                ]);
            }

            $this->clearCache();

            return $newConfig->load('hierarchyLevels');
        });
    }

    // ── Hierarchy Levels ─────────────────────────────────────────────────────

    /**
     * Create hierarchy levels for a configuration.
     */
    public function createHierarchyLevels(OrganizationConfig $config, array $levels): Collection
    {
        return DB::transaction(function () use ($config, $levels) {
            $created = collect();

            foreach ($levels as $levelData) {
                $level = HierarchyLevelDefinition::create([
                    'organization_config_id' => $config->id,
                    'level_order'            => $levelData['level_order'],
                    'level_code'             => $levelData['level_code'],
                    'level_name'             => $levelData['level_name'],
                    'level_description'      => $levelData['level_description'] ?? null,
                    'level_icon'             => $levelData['level_icon']  ?? 'circle',
                    'level_color'            => $levelData['level_color'] ?? '#0066FF',
                    'can_have_users'         => $levelData['can_have_users']    ?? true,
                    'can_have_managers'      => $levelData['can_have_managers'] ?? true,
                    'is_active'              => $levelData['is_active'] ?? true,
                    'metadata'               => $levelData['metadata'] ?? [],
                ]);

                $created->push($level);
            }

            return new Collection($created->all());
        });
    }

    /**
     * Update hierarchy levels for a configuration (upsert / deactivate removed).
     */
    public function updateHierarchyLevels(OrganizationConfig $config, array $levels): Collection
    {
        return DB::transaction(function () use ($config, $levels) {
            $existing     = $config->hierarchyLevels()->get()->keyBy('id');
            $updatedLevels = collect();

            foreach ($levels as $levelData) {
                if (!empty($levelData['id']) && $existing->has($levelData['id'])) {
                    $level = $existing->get($levelData['id']);
                    $level->update([
                        'level_order'       => $levelData['level_order'],
                        'level_code'        => $levelData['level_code'],
                        'level_name'        => $levelData['level_name'],
                        'level_description' => $levelData['level_description'] ?? $level->level_description,
                        'level_icon'        => $levelData['level_icon']  ?? $level->level_icon,
                        'level_color'       => $levelData['level_color'] ?? $level->level_color,
                        'can_have_users'    => $levelData['can_have_users']    ?? $level->can_have_users,
                        'can_have_managers' => $levelData['can_have_managers'] ?? $level->can_have_managers,
                        'is_active'         => $levelData['is_active'] ?? $level->is_active,
                        'metadata'          => array_merge($level->metadata ?? [], $levelData['metadata'] ?? []),
                    ]);
                    $updatedLevels->push($level);
                    $existing->forget($levelData['id']);
                } else {
                    $level = HierarchyLevelDefinition::create([
                        'organization_config_id' => $config->id,
                        'level_order'            => $levelData['level_order'],
                        'level_code'             => $levelData['level_code'],
                        'level_name'             => $levelData['level_name'],
                        'level_description'      => $levelData['level_description'] ?? null,
                        'level_icon'             => $levelData['level_icon']  ?? 'circle',
                        'level_color'            => $levelData['level_color'] ?? '#0066FF',
                        'can_have_users'         => $levelData['can_have_users']    ?? true,
                        'can_have_managers'      => $levelData['can_have_managers'] ?? true,
                        'is_active'              => $levelData['is_active'] ?? true,
                        'metadata'               => $levelData['metadata'] ?? [],
                    ]);
                    $updatedLevels->push($level);
                }
            }

            // Deactivate levels that were not included in the update
            foreach ($existing as $level) {
                $level->update(['is_active' => false]);
            }

            return new Collection($updatedLevels->all());
        });
    }

    // ── Read Helpers ──────────────────────────────────────────────────────────

    /**
     * Get all active organization configurations with levels.
     */
    public function getActiveConfigurations(): Collection
    {
        return OrganizationConfig::with(['hierarchyLevels' => fn($q) => $q->active()->orderBy('level_order')])
            ->withCount(['organizationalUnits as active_units_count' => fn($q) => $q->where('is_active', true)])
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get a configuration with usage stats and level usage stats.
     */
    public function getConfigurationWithStats(string $configId): ?OrganizationConfig
    {
        $config = OrganizationConfig::with([
            'hierarchyLevels' => fn($q) => $q->orderBy('level_order'),
        ])->find($configId);

        if (!$config) {
            return null;
        }

        $config->loadCount([
            'organizationalUnits',
            'organizationalUnits as active_units_count' => fn($q) => $q->where('is_active', true),
        ]);

        foreach ($config->hierarchyLevels as $level) {
            $level->loadCount([
                'organizationalUnits',
                'organizationalUnits as active_units_count' => fn($q) => $q->where('is_active', true),
            ]);
        }

        return $config;
    }

    /**
     * Build a hierarchy tree array for a configuration.
     */
    public function getHierarchyTree(string $configId): array
    {
        $units = OrganizationalUnit::with('levelDefinition')
            ->where('organization_config_id', $configId)
            ->where('is_active', true)
            ->get();

        $config = OrganizationConfig::with(['hierarchyLevels' => fn($q) => $q->active()->orderBy('level_order')])
            ->find($configId);

        return [
            'config' => $config,
            'tree'   => $this->buildTree($units),
        ];
    }

    /**
     * Recursively build a nested tree array from a flat collection.
     */
    protected function buildTree(Collection $units, ?string $parentId = null): array
    {
        $tree = [];

        foreach ($units->where('parent_id', $parentId) as $unit) {
            $tree[] = [
                'id'          => $unit->id,
                'name'        => $unit->name,
                'level_code'  => $unit->level_code,
                'level_order' => $unit->level_order,
                'level_name'  => $unit->getLevelName(),
                'children'    => $this->buildTree($units, $unit->id),
            ];
        }

        return $tree;
    }

    // ── Role Permissions ──────────────────────────────────────────────────────

    /**
     * Create role hierarchy permissions in bulk.
     */
    public function createRolePermissions(array $permissions): Collection
    {
        return DB::transaction(function () use ($permissions) {
            $created = collect();

            foreach ($permissions as $permData) {
                $created->push(RoleHierarchyPermission::create([
                    'role_name'                    => $permData['role_name'],
                    'organization_config_id'       => $permData['organization_config_id'],
                    'hierarchy_level_definition_id' => $permData['hierarchy_level_definition_id'] ?? null,
                    'min_level_order'              => $permData['min_level_order'] ?? null,
                    'max_level_order'              => $permData['max_level_order'] ?? null,
                    'allowed_level_codes'          => $permData['allowed_level_codes'] ?? [],
                    'can_manage_users'             => $permData['can_manage_users']  ?? false,
                    'can_create_units'             => $permData['can_create_units']  ?? false,
                    'can_edit_units'               => $permData['can_edit_units']    ?? false,
                    'can_delete_units'             => $permData['can_delete_units']  ?? false,
                    'is_active'                    => $permData['is_active'] ?? true,
                ]));
            }

            return $created;
        });
    }

    // ── Cache ─────────────────────────────────────────────────────────────────

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . ':active_config');
    }

    // ── Missing Helpers ───────────────────────────────────────────────────────

    public function roleCanAccessLevel(string $roleName, string $levelCode): bool
    {
        if (in_array($roleName, ['superadmin', 'admin', 'DIR_COMMERCIALE'])) {
            return true;
        }

        $permissions = RoleHierarchyPermission::where('role_name', $roleName)
            ->where('is_active', true)
            ->get();
            
        foreach ($permissions as $permission) {
            if ($permission->hierarchyLevel && $permission->hierarchyLevel->level_code === $levelCode) {
                return true;
            }
            if (is_array($permission->allowed_level_codes) && in_array($levelCode, $permission->allowed_level_codes)) {
                return true;
            }
            $level = $this->getLevelByCode($levelCode);
            if ($level && $permission->min_level_order && $permission->max_level_order) {
                if ($level->level_order >= $permission->min_level_order && $level->level_order <= $permission->max_level_order) {
                    return true;
                }
            }
        }
        
        return false;
    }

    public function getHierarchyLevels(): Collection
    {
        return HierarchyLevelDefinition::where('is_active', true)->orderBy('level_order')->get();
    }

    public function getAccessibleLevelsForRole(string $roleName): Collection
    {
        if (in_array($roleName, ['superadmin', 'admin', 'DIR_COMMERCIALE'])) {
            return $this->getHierarchyLevels();
        }

        $allLevels = $this->getHierarchyLevels();
        return $allLevels->filter(function($level) use ($roleName) {
            return $this->roleCanAccessLevel($roleName, $level->level_code);
        });
    }

    public function getLevelByOrder(int $order): ?HierarchyLevelDefinition
    {
        return HierarchyLevelDefinition::where('level_order', $order)->where('is_active', true)->first();
    }

    public function getLevelByCode(string $code): ?HierarchyLevelDefinition
    {
        return HierarchyLevelDefinition::where('level_code', $code)->where('is_active', true)->first();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function isConfigurationInUse(OrganizationConfig $config): bool
    {
        return OrganizationalUnit::where('organization_config_id', $config->id)
            ->where('is_active', true)
            ->exists();
    }
}
