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
                'code' => 'CP-50MG',
                'nom' => 'CardioPulse 50mg',
                'gamme' => 'Cardiologie',
                'description' => 'Valsartan / Amlodipine - Comprimé pelliculé',
                'pght' => 4850.00,
                'ppv' => 5800.00,
                'devise' => 'XOF',
                'stock_actuel' => 500,
                'stock_alerte' => 50,
                'statut' => 'actif',
                'meta' => ['dosage' => '50mg', 'presentation' => 'Boîte de 30 comprimés', 'indication' => 'Hypertension artérielle & Prévention AVC']
            ],
            [
                'code' => 'VPN-100',
                'nom' => 'VasoProtect Neo',
                'gamme' => 'Vasculaire',
                'description' => 'Nifédipine LP - Gélule à libération prolongée',
                'pght' => 6200.00,
                'ppv' => 7400.00,
                'devise' => 'XOF',
                'stock_actuel' => 350,
                'stock_alerte' => 30,
                'statut' => 'actif',
                'meta' => ['dosage' => '100mg', 'presentation' => 'Boîte de 28 gélules', 'indication' => 'Insuffisance veineuse & Hypertensions sévères']
            ],
            [
                'code' => 'LS-20MG',
                'nom' => 'LipidoStat 20mg',
                'gamme' => 'Metabolisme',
                'description' => 'Atorvastatine - Comprimé sécable',
                'pght' => 3900.00,
                'ppv' => 4600.00,
                'devise' => 'XOF',
                'stock_actuel' => 420,
                'stock_alerte' => 40,
                'statut' => 'actif',
                'meta' => ['dosage' => '20mg', 'presentation' => 'Boîte de 30 comprimés', 'indication' => 'Hypercholestérolémie & Dyslipidémie']
            ],
            [
                'code' => 'DBX-500',
                'nom' => 'DiaBex 500mg',
                'gamme' => 'Metabolisme',
                'description' => 'Metformine chlorhydrate',
                'pght' => 2800.00,
                'ppv' => 3300.00,
                'devise' => 'XOF',
                'stock_actuel' => 600,
                'stock_alerte' => 60,
                'statut' => 'actif',
                'meta' => ['dosage' => '500mg', 'presentation' => 'Boîte de 60 comprimés', 'indication' => 'Diabète de type 2']
            ],
            [
                'code' => 'PEDI-002',
                'nom' => 'PediaCure Sirop 100ml',
                'gamme' => 'Pédiatrie',
                'description' => 'Amoxicilline / Acide Clavulanique - Sirop 100ml',
                'pght' => 4500.00,
                'ppv' => 5400.00,
                'devise' => 'XOF',
                'stock_actuel' => 300,
                'stock_alerte' => 30,
                'statut' => 'actif',
                'meta' => ['dosage' => 'Sirop 250mg/5ml', 'presentation' => 'Flacon 100ml', 'indication' => 'Infections ORL & pédiatriques']
            ],
            [
                'code' => 'NEURO-10',
                'nom' => 'NeuroMed 10mg',
                'gamme' => 'Neurologie',
                'description' => 'Memantine HCl - Comprimé pelliculé',
                'pght' => 8500.00,
                'ppv' => 9900.00,
                'devise' => 'XOF',
                'stock_actuel' => 150,
                'stock_alerte' => 20,
                'statut' => 'actif',
                'meta' => ['dosage' => '10mg', 'presentation' => 'Boîte de 30 comprimés', 'indication' => 'Troubles cognitifs & mémoire']
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
