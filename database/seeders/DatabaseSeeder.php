<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Créer les 5 rôles réels en premier (source de vérité)
        $this->call([RoleAndPermissionSeeder::class]);

        // 2. Create Scope Permissions
        $scopePermissions = [
            'scope:global',
            'scope:country',
            'scope:subsidiary',
            'scope:region',
            'scope:territory',
            'scope:zone',
            'scope:sector',
            'scope:team'
        ];
        
        foreach ($scopePermissions as $perm) {
            \App\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 3. Assigner les permissions de scope aux rôles réels
        $rolesConfig = [
            'super-admin' => ['scope:global'],
            'directeur_regional' => ['scope:country'],
            'directeur-commercial' => ['scope:subsidiary'],
            'directeur-marketing' => ['scope:subsidiary'],
            'sales_manager' => ['scope:subsidiary'],
            'superviseur' => ['scope:subsidiary'],
            'delegue-medical' => ['scope:team']
        ];

        foreach ($rolesConfig as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        $seeders = [
            SpecialitySeeder::class,
            SegmentationSeeder::class,
            PotentialSeeder::class,
            InfluenceSeeder::class,
        ];
        
        // These base seeders can just run or we can conditionally add them too
        $seeders = array_merge($seeders, [
            AccountTypeSeeder::class,
            GalenicFormSeeder::class,
            ActivityTypeSeeder::class,
            AccountTypeActivityTypeSeeder::class,
            ActivityResultSeeder::class,
            EventFormatSeeder::class,
            SettingSeeder::class,
            ObjectifTypeSeeder::class,
            DuoActivityTypesSeeder::class,
            DuoActivityTypeSeeder::class,
            TrioActivityTypeSeeder::class,
        ]);

        $this->call($seeders);
    }
}
