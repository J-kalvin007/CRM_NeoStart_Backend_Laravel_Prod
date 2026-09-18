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
        $users = User::all();
        $products = Product::all();

        if ($users->isNotEmpty() && $products->isNotEmpty()) {
            foreach ($users as $u) {
                foreach ($products as $index => $p) {
                    $initialQty = 50 + ($index * 25);
                    $distQty = 5 + ($index * 3);
                    $lotNum = 'LOT-2026-' . ($p->code ?? ('PRD' . ($index + 1)));

                    // Attribution de dotation initiale au délégué
                    SampleMovement::create([
                        'user_id' => $u->id,
                        'product_id' => $p->id,
                        'movement_type' => 'attribution',
                        'quantity' => $initialQty,
                        'lot_number' => $lotNum,
                        'remarques' => 'Dotation trimestrielle initiale confiée au délégué.'
                    ]);

                    // Distribution d'échantillons
                    SampleMovement::create([
                        'user_id' => $u->id,
                        'product_id' => $p->id,
                        'movement_type' => 'distribution',
                        'quantity' => $distQty,
                        'lot_number' => $lotNum,
                        'remarques' => 'Remise d\'échantillons lors des visites médicales terrain.'
                    ]);
                }
            }
        }
    }
}
