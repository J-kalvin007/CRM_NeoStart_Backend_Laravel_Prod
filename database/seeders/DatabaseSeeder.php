<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Country;
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

        // 2. Create sample Hierarchy
        $country = Country::firstOrCreate(['iso_code' => 'TGO'], [
            'name' => 'Togo',
            'currency' => 'XOF',
            'language' => 'fr',
            'timezone' => 'Africa/Lome',
            'status' => true
        ]);

        // 3. Create Super Admin
        $admin = User::firstOrCreate(['email' => 'admin@crm.local'], [
            'name' => 'Super Administrateur',
            'password' => Hash::make('password'),
            'status' => true
        ]);

        if (!$admin->hasRole('super-admin')) {
            $admin->assignRole('super-admin');
        }

        $seeders = [
            SpecialitySeeder::class,
            SegmentationSeeder::class,
            PotentialSeeder::class,
            InfluenceSeeder::class,
        ];
        
        // These base seeders can just run or we can conditionally add them too
        $seeders = array_merge($seeders, [
            GalenicFormSeeder::class,
            ActivityTypeSeeder::class,
            ActivityResultSeeder::class,
            EventFormatSeeder::class,
            SettingSeeder::class,
            ObjectifTypeSeeder::class,
            DuoActivityTypesSeeder::class,
            DuoActivityTypeSeeder::class,
            TrioActivityTypeSeeder::class,
            UserSeeder::class,
        ]);

        if (\App\Models\Account::count() === 0) $seeders[] = AccountSeeder::class;
        if (\App\Models\Contact::count() === 0) $seeders[] = ContactSeeder::class;
        if (\App\Models\Product::count() === 0) $seeders[] = ProductSeeder::class;
        if (\App\Models\Activity::count() === 0) $seeders[] = ActivitySeeder::class;

        $seeders = array_merge($seeders, [
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

        $this->call($seeders);
    }
}
