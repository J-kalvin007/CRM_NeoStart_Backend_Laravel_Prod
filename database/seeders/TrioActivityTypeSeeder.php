<?php

namespace Database\Seeders;

use App\Models\TrioActivityType;
use Illuminate\Database\Seeder;

class TrioActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Visite Stratégique',
                'code' => 'VIS_STRATEGIQUE',
                'description' => 'Visite stratégique impliquant le Sales Manager, le superviseur et le délégué',
                'is_active' => true,
            ],
            [
                'name' => 'Revue de Performance',
                'code' => 'REV_PERFORMANCE',
                'description' => 'Revue de performance conjointe pour évaluer les résultats du délégué',
                'is_active' => true,
            ],
            [
                'name' => 'Planification Territoire',
                'code' => 'PLAN_TERRITOIRE',
                'description' => 'Planification conjointe du territoire et des objectifs',
                'is_active' => true,
            ],
            [
                'name' => 'Négociation de commande',
                'code' => 'NEGOCIATION',
                'description' => 'Négociation commerciale et commande',
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            TrioActivityType::firstOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
