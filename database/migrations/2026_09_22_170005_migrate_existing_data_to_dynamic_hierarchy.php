<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Créer la configuration par défaut
        $defaultConfigId = $this->createDefaultConfiguration();
        
        // 2. Créer les définitions de niveaux par défaut
        $levelDefinitions = $this->createDefaultLevelDefinitions($defaultConfigId);
        
        // 3. Migrer les données organizational_units existantes
        $this->migrateOrganizationalUnits($defaultConfigId, $levelDefinitions);
        
        // 4. Mettre à jour user_scopes avec la configuration
        $this->migrateUserScopes($defaultConfigId);
        
        // 5. Créer les permissions par défaut pour les rôles existants
        $this->createDefaultRolePermissions($defaultConfigId);
    }

    /**
     * Créer la configuration par défaut
     */
    private function createDefaultConfiguration(): string
    {
        $configId = (string) Str::uuid();
        
        DB::table('organization_configs')->insert([
            'id' => $configId,
            'name' => 'Configuration CRM Par Défaut',
            'code' => 'crm_default',
            'description' => 'Configuration hiérarchique par défaut du système CRM',
            'is_active' => true,
            'max_hierarchy_levels' => 7,
            'settings' => json_encode([
                'auto_assign_users' => true,
                'inherit_permissions' => true,
                'enforce_hierarchy' => true
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return $configId;
    }

    /**
     * Créer les définitions de niveaux par défaut
     */
    private function createDefaultLevelDefinitions(string $configId): array
    {
        $levels = [
            [
                'order' => 1, 'name' => 'Pays', 'code' => 'country', 
                'icon' => 'globe', 'color' => '#2563eb',
                'can_have_users' => false, 'can_have_managers' => true
            ],
            [
                'order' => 2, 'name' => 'Filiale', 'code' => 'subsidiary',
                'icon' => 'building-2', 'color' => '#7c3aed', 
                'can_have_users' => false, 'can_have_managers' => true
            ],
            [
                'order' => 3, 'name' => 'Région', 'code' => 'region',
                'icon' => 'map', 'color' => '#dc2626',
                'can_have_users' => false, 'can_have_managers' => true
            ],
            [
                'order' => 4, 'name' => 'Territoire', 'code' => 'territory', 
                'icon' => 'map-pin', 'color' => '#ea580c',
                'can_have_users' => false, 'can_have_managers' => true
            ],
            [
                'order' => 5, 'name' => 'Zone', 'code' => 'zone',
                'icon' => 'circle-dot', 'color' => '#ca8a04',
                'can_have_users' => true, 'can_have_managers' => true
            ],
            [
                'order' => 6, 'name' => 'Secteur', 'code' => 'sector', 
                'icon' => 'target', 'color' => '#16a34a',
                'can_have_users' => true, 'can_have_managers' => true
            ],
            [
                'order' => 7, 'name' => 'Équipe', 'code' => 'team',
                'icon' => 'users', 'color' => '#0891b2',
                'can_have_users' => true, 'can_have_managers' => true
            ]
        ];

        $definitions = [];
        
        foreach ($levels as $level) {
            $definitionId = (string) Str::uuid();
            
            DB::table('hierarchy_level_definitions')->insert([
                'id' => $definitionId,
                'organization_config_id' => $configId,
                'level_order' => $level['order'],
                'level_name' => $level['name'],
                'level_code' => $level['code'],
                'level_icon' => $level['icon'],
                'level_color' => $level['color'],
                'can_have_users' => $level['can_have_users'],
                'can_have_managers' => $level['can_have_managers'],
                'is_required' => false,
                'display_settings' => json_encode([
                    'show_in_breadcrumb' => true,
                    'show_in_selector' => true,
                    'default_expanded' => false
                ]),
                'validation_rules' => json_encode([
                    'name_required' => true,
                    'name_min_length' => 2,
                    'name_max_length' => 255
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $definitions[$level['code']] = $definitionId;
        }
        
        return $definitions;
    }

    /**
     * Migrer les données organizational_units existantes
     */
    private function migrateOrganizationalUnits(string $configId, array $levelDefinitions): void
    {
        // Récupérer toutes les unités organisationnelles existantes
        $units = DB::table('organizational_units')->get();
        
        foreach ($units as $unit) {
            // Déterminer le level_definition_id basé sur le type
            $levelDefinitionId = $levelDefinitions[$unit->type] ?? null;
            
            // Déterminer l'ordre du niveau
            $levelOrder = match($unit->type) {
                'country' => 1,
                'subsidiary' => 2,
                'region' => 3,
                'territory' => 4,
                'zone' => 5,
                'sector' => 6,
                'team' => 7,
                default => 0
            };
            
            // Mettre à jour l'unité avec les nouvelles colonnes
            DB::table('organizational_units')
                ->where('id', $unit->id)
                ->update([
                    'organization_config_id' => $configId,
                    'level_definition_id' => $levelDefinitionId,
                    'level_order' => $levelOrder,
                    'level_code' => $unit->type,
                    'is_active' => true,
                    'display_order' => 0,
                    'updated_at' => now()
                ]);
        }
    }

    /**
     * Mettre à jour user_scopes avec la configuration
     */
    private function migrateUserScopes(string $configId): void
    {
        DB::table('user_scopes')->update([
            'organization_config_id' => $configId,
            'is_primary' => true,
            'is_active' => true,
            'updated_at' => now()
        ]);
    }

    /**
     * Créer les permissions par défaut pour les rôles existants
     */
    private function createDefaultRolePermissions(string $configId): void
    {
        // Récupérer tous les rôles existants
        $roles = DB::table('roles')->get();
        
        foreach ($roles as $role) {
            $permissions = $this->getDefaultPermissionsForRole($role->name);
            
            if (!empty($permissions)) {
                DB::table('role_hierarchy_permissions')->insert([
                    'id' => (string) Str::uuid(),
                    'role_id' => $role->id,
                    'organization_config_id' => $configId,
                    'allowed_level_codes' => json_encode($permissions['allowed_levels']),
                    'can_manage_level_codes' => json_encode($permissions['can_manage_levels']),
                    'access_scope' => $permissions['access_scope'],
                    'can_create_units' => $permissions['can_create_units'],
                    'can_edit_units' => $permissions['can_edit_units'],
                    'can_delete_units' => $permissions['can_delete_units'],
                    'can_assign_users' => $permissions['can_assign_users'],
                    'restrictions' => json_encode($permissions['restrictions']),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }

    /**
     * Obtenir les permissions par défaut selon le rôle
     */
    private function getDefaultPermissionsForRole(string $roleName): ?array
    {
        return match(strtolower($roleName)) {
            'super-admin', 'superadmin' => [
                'allowed_levels' => ['country', 'subsidiary', 'region', 'territory', 'zone', 'sector', 'team'],
                'can_manage_levels' => ['country', 'subsidiary', 'region', 'territory', 'zone', 'sector', 'team'],
                'access_scope' => 'global',
                'can_create_units' => true,
                'can_edit_units' => true,
                'can_delete_units' => true,
                'can_assign_users' => true,
                'restrictions' => []
            ],
            'directeur-commercial', 'directeur_commercial' => [
                'allowed_levels' => ['subsidiary', 'region', 'territory', 'zone', 'sector', 'team'],
                'can_manage_levels' => ['region', 'territory', 'zone', 'sector', 'team'],
                'access_scope' => 'team_perimeter',
                'can_create_units' => false,
                'can_edit_units' => true,
                'can_delete_units' => false,
                'can_assign_users' => true,
                'restrictions' => ['cannot_access_other_subsidiaries']
            ],
            'directeur-marketing', 'directeur_marketing' => [
                'allowed_levels' => ['subsidiary', 'region', 'territory', 'zone', 'sector', 'team'],
                'can_manage_levels' => ['team'],
                'access_scope' => 'team_perimeter',
                'can_create_units' => false,
                'can_edit_units' => false,
                'can_delete_units' => false,
                'can_assign_users' => false,
                'restrictions' => ['read_only_geographic_data']
            ],
            'superviseur' => [
                'allowed_levels' => ['territory', 'zone', 'sector', 'team'],
                'can_manage_levels' => ['team'],
                'access_scope' => 'own_perimeter',
                'can_create_units' => false,
                'can_edit_units' => false,
                'can_delete_units' => false,
                'can_assign_users' => false,
                'restrictions' => ['limited_to_assigned_territory']
            ],
            'delegue-medical', 'delegue_medical' => [
                'allowed_levels' => ['sector', 'team'],
                'can_manage_levels' => [],
                'access_scope' => 'own_perimeter',
                'can_create_units' => false,
                'can_edit_units' => false,
                'can_delete_units' => false,
                'can_assign_users' => false,
                'restrictions' => ['read_only_access', 'own_data_only']
            ],
            default => null
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer les données créées
        DB::table('role_hierarchy_permissions')->truncate();
        DB::table('user_scopes')->update([
            'organization_config_id' => null,
            'is_primary' => null,
            'permissions' => null,
            'expires_at' => null,
            'is_active' => null
        ]);
        DB::table('organizational_units')->update([
            'organization_config_id' => null,
            'level_definition_id' => null,
            'level_order' => null,
            'level_code' => null,
            'is_active' => null,
            'display_order' => null
        ]);
        DB::table('hierarchy_level_definitions')->truncate();
        DB::table('organization_configs')->truncate();
    }
};