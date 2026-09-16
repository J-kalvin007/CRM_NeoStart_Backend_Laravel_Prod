<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SampleMovement;
use App\Models\Product;
use App\Models\User;

class StockSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $product = Product::first();

        if ($user && $product) {
            // Attribution initiale pour le délégué
            SampleMovement::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'movement_type' => 'attribution',
                'quantity' => 50,
                'lot_number' => 'LOT-2026-CARD01',
                'remarques' => 'Dotation trimestrielle initiale confiée au délégué.'
            ]);

            // Distribution à un médecin
            SampleMovement::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'movement_type' => 'distribution',
                'quantity' => 5,
                'lot_number' => 'LOT-2026-CARD01',
                'remarques' => 'Remise de 5 boîtes d\'échantillons lors de la visite médicale.'
            ]);
        }
    }
}
