<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Nettoyage du cache des permissions Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Création des rôles
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'delegue-medical']);
        Role::firstOrCreate(['name' => 'superviseur']);
        Role::firstOrCreate(['name' => 'directeur-commercial']);
        Role::firstOrCreate(['name' => 'directeur-marketing']);
        
        // (Optionnel) Création de permissions globales
        // Permission::firstOrCreate(['name' => 'create campaigns']);
        // $manager->givePermissionTo('create campaigns');
    }
}
