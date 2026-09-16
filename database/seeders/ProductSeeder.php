<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Subsidiary;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $subsidiary = Subsidiary::first();
        $medType = ProductType::firstOrCreate(['code' => 'MED'], ['libelle' => 'Médicament']);

        $products = [
            [
                'code' => 'CARD-001',
                'nom' => 'CardioProtect 50mg',
                'gamme' => 'Cardiologie & Vasculaire',
                'description' => 'Antihypertenseur de dernière génération pour la prévention des AVC.',
                'prix_unitaire' => 12500.00,
                'devise' => 'XOF',
                'stock_actuel' => 500,
                'stock_alerte' => 50,
                'statut' => 'actif',
                'meta' => ['dosage' => '50mg Comprimé', 'posology' => '1 cp/jour']
            ],
            [
                'code' => 'PEDI-002',
                'nom' => 'PediaCure Sirop 100ml',
                'gamme' => 'Pédiatrie & Infectiologie',
                'description' => 'Antibiotique à large spectre adapté aux nourrissons et enfants.',
                'prix_unitaire' => 4500.00,
                'devise' => 'XOF',
                'stock_actuel' => 300,
                'stock_alerte' => 30,
                'statut' => 'actif',
                'meta' => ['dosage' => 'Sirop 250mg/5ml', 'posology' => '1 cuillère x 3/jour']
            ],
            [
                'code' => 'ANAL-003',
                'nom' => 'Neoflam Fast 400mg',
                'gamme' => 'Anti-inflammatoires',
                'description' => 'Anti-inflammatoire non stéroïdien à action rapide.',
                'prix_unitaire' => 3800.00,
                'devise' => 'XOF',
                'stock_actuel' => 400,
                'stock_alerte' => 40,
                'statut' => 'actif',
                'meta' => ['dosage' => '400mg Gélule', 'posology' => '1 gélule en cas de crise']
            ]
        ];

        if ($subsidiary) {
            foreach ($products as $p) {
                Product::firstOrCreate(
                    ['code' => $p['code']],
                    array_merge($p, [
                        'subsidiary_id' => $subsidiary->id,
                        'product_type_id' => $medType?->id
                    ])
                );
            }
        }
    }
}
