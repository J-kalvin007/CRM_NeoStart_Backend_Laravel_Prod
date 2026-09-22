<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Subsidiary;

class IndicatorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subsidiary = Subsidiary::first();
        if (!$subsidiary) {
            $this->command->info('No subsidiary found. Please seed subsidiaries first.');
            return;
        }

        $indicators = [
            [
                'id' => Str::uuid(),
                'name' => 'Nombre de visites',
                'code' => 'nombre_visites',
                'description' => 'Nombre total de visites effectuées par le délégué.',
                'calculation_type' => 'count',
                'data_source_model' => 'Activity',
                'data_source_field' => 'id',
                'unit' => 'visites',
                'is_active' => true,
                'subsidiary_id' => $subsidiary->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Couverture Prescripteurs',
                'code' => 'couverture_contacts',
                'description' => 'Pourcentage de prescripteurs visités.',
                'calculation_type' => 'percentage',
                'data_source_model' => 'Contact',
                'data_source_field' => 'id',
                'unit' => '%',
                'is_active' => true,
                'subsidiary_id' => $subsidiary->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Chiffre d\'Affaires',
                'code' => 'ca_ventes',
                'description' => 'Chiffre d\'affaires total généré.',
                'calculation_type' => 'sum',
                'data_source_model' => 'Sale',
                'data_source_field' => 'amount',
                'unit' => 'FCFA',
                'is_active' => true,
                'subsidiary_id' => $subsidiary->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Placement Échantillons',
                'code' => 'nb_echantillons',
                'description' => 'Nombre d\'échantillons placés.',
                'calculation_type' => 'sum',
                'data_source_model' => 'SampleMovement',
                'data_source_field' => 'quantity',
                'unit' => 'unités',
                'is_active' => true,
                'subsidiary_id' => $subsidiary->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Prises de Commandes',
                'code' => 'nb_commandes',
                'description' => 'Nombre de commandes enregistrées.',
                'calculation_type' => 'count',
                'data_source_model' => 'Order',
                'data_source_field' => 'id',
                'unit' => 'commandes',
                'is_active' => true,
                'subsidiary_id' => $subsidiary->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('indicators')->insert($indicators);
        $this->command->info('Indicators seeded successfully!');
    }
}
