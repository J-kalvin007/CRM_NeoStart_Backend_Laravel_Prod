<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DigitalSupportSeeder extends Seeder
{
    public function run(): void
    {
        $prodCardio = DB::table('products')->where('nom', 'LIKE', '%CardioPulse%')->value('id') ?? DB::table('products')->first()->id ?? null;

        if ($prodCardio) {
            DB::table('digital_supports')->insert([
                [
                    'id' => Str::uuid(),
                    'title' => 'ADV CardioPulse 2026 - E-Detailing Interactif',
                    'product_id' => $prodCardio,
                    'version' => '3.2',
                    'total_slides' => 12,
                    'file_path' => '/storage/adv/cardiopulse_interactive_v3.zip',
                    'thumbnail_url' => '/storage/adv/thumbnails/cardiopulse_thumb.png',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }
    }
}
