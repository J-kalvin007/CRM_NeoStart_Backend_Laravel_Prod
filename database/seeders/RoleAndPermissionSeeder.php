<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Nettoyage du cache des permissions Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Création des rôles
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $delegue = Role::firstOrCreate(['name' => 'delegue']);
        
        // (Optionnel) Création de permissions globales
        // Permission::firstOrCreate(['name' => 'create campaigns']);
        // $manager->givePermissionTo('create campaigns');
    }
}
