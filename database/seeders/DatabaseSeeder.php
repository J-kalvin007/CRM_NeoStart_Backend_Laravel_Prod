<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Country;
use App\Models\Subsidiary;
use App\Models\Region;
use App\Models\Territory;
use App\Models\Zone;
use App\Models\Sector;
use App\Models\Team;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
            'superadmin' => ['scope:global'],
            'directeur_regional' => ['scope:country'],
            'directeur_commercial' => ['scope:subsidiary'],
            'directeur_marketing' => ['scope:subsidiary'],
            'superviseur' => ['scope:subsidiary'],
            'delegue_medical' => ['scope:team']
        ];

        foreach ($rolesConfig as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        // 2. Create sample Hierarchy
        $country = Country::firstOrCreate(['iso_code' => 'FRA'], [
            'name' => 'France',
            'currency' => 'EUR',
            'language' => 'fr',
            'timezone' => 'Europe/Paris',
            'status' => true
        ]);

        $subsidiary = Subsidiary::firstOrCreate(['code' => 'SUB-FR'], [
            'country_id' => $country->id,
            'name' => 'Filiale France',
            'status' => true
        ]);

        $region = Region::firstOrCreate(['name' => 'Île-de-France'], [
            'subsidiary_id' => $subsidiary->id
        ]);

        $territory = Territory::firstOrCreate(['name' => 'Paris'], [
            'region_id' => $region->id
        ]);

        $zone = Zone::firstOrCreate(['name' => 'Paris Centre', 'code' => 'Z-PC'], [
            'territory_id' => $territory->id
        ]);

        $sector = Sector::firstOrCreate(['name' => 'Secteur 1 (Louvre)', 'code' => 'S-LOU'], [
            'zone_id' => $zone->id
        ]);

        $team = Team::firstOrCreate(['name' => 'Équipe Paris Centre']);

        // 3. Create Super Admin
        $admin = User::firstOrCreate(['email' => 'admin@crm.local'], [
            'name' => 'Super Administrateur',
            'password' => Hash::make('password'),
            'status' => true
        ]);

        if (!$admin->hasRole('superadmin')) {
            $admin->assignRole('superadmin');
        }

        $this->call([
            SpecialitySeeder::class,
            SegmentationSeeder::class,
            PotentialSeeder::class,
            InfluenceSeeder::class,
            GalenicFormSeeder::class,
            ActivityTypeSeeder::class,
            ActivityResultSeeder::class,
            EventFormatSeeder::class,
            RoleAndPermissionSeeder::class,
            SettingSeeder::class,
            ObjectifTypeSeeder::class,
            DuoActivityTypesSeeder::class,
            PharmaSeeder::class,
            UserSeeder::class,
            AccountSeeder::class,
            ContactSeeder::class,
            ProductSeeder::class,
            ActivitySeeder::class,
            StockSeeder::class,
            EventSeeder::class,
            ExpenseSeeder::class,
            CampaignSeeder::class,
            NotificationSeeder::class,
            DocumentSeeder::class,
            DigitalSupportSeeder::class,
            PharmacovigilanceSeeder::class,
            ConversationMessageSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
