<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Types d'objectif proposés dans l'application (création d'objectif par le superviseur).
 */
class ObjectifTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'nombre_visites', 'name' => 'Visites Médicales', 'unit' => 'visites'],
            ['code' => 'ca_ventes', 'name' => "Chiffre d'Affaires", 'unit' => 'FCFA'],
            ['code' => 'couverture_contacts', 'name' => 'Couverture Prescripteurs', 'unit' => '%'],
            ['code' => 'nb_echantillons', 'name' => 'Placement Échantillons', 'unit' => 'unités'],
            ['code' => 'nb_commandes', 'name' => 'Prises de Commandes', 'unit' => 'commandes'],
            ['code' => 'autre', 'name' => 'Autre objectif spécifique', 'unit' => 'unités'],
        ];

        foreach ($types as $type) {
            $existing = DB::table('objectif_types')->where('code', $type['code'])->first();
            if ($existing) {
                DB::table('objectif_types')->where('id', $existing->id)->update($type + ['updated_at' => now()]);
            } else {
                DB::table('objectif_types')->insert($type + [
                    'id' => (string) Str::uuid(),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
