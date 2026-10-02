<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;

class SystemPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'restriction:single_scope',
            // d'autres permissions systèmes futures peuvent être ajoutées ici
        ];

        foreach ($permissions as $permission) {
            $exists = Permission::where('name', $permission)->exists();
            if (!$exists) {
                Permission::create([
                    'id' => (string) Str::uuid(),
                    'name' => $permission,
                    'guard_name' => 'web' // ou api, selon la configuration par défaut du projet
                ]);
            }
        }
    }
}
