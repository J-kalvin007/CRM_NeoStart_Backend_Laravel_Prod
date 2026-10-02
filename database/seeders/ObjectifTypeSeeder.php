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
            ['code' => 'nombre_visites', 'name' => 'Nombre de visites terrain', 'unit' => 'visites'],
            ['code' => 'couverture_categorie_a', 'name' => 'Taux de couverture des cibles A', 'unit' => '%'],
            ['code' => 'produit_prioritaire', 'name' => 'Présentations produit prioritaire', 'unit' => 'présentations'],
            ['code' => 'ca_ventes', 'name' => "Chiffre d'affaires ventes", 'unit' => 'FCFA'],
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
