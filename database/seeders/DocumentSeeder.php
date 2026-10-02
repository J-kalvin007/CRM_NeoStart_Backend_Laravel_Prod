<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = \App\Models\Product::all();
        if ($products->isNotEmpty()) {
            foreach ($products as $product) {
                \App\Models\Document::firstOrCreate(
                    [
                        'documentable_id' => $product->id,
                        'documentable_type' => 'App\\Models\\Product',
                        'type' => 'presentation',
                    ],
                    [
                        'title' => "E-Detailing {$product->nom} — Présentation Tablette",
                        'description' => "Support e-Detailing officiel pour la présentation de {$product->nom}.",
                        'file_path' => 'storage/docs/presentation_' . strtolower($product->code ?: 'prod') . '.pdf',
                        'version' => '1.0',
                        'is_active' => true,
                        'expiration_date' => now()->addYears(2),
                        'status' => 'approved',
                    ]
                );

                \App\Models\Document::firstOrCreate(
                    [
                        'documentable_id' => $product->id,
                        'documentable_type' => 'App\\Models\\Product',
                        'type' => 'pdf',
                    ],
                    [
                        'title' => "RCP & Notice Technique {$product->nom}",
                        'description' => "Fiche Posologie et Résumé des Caractéristiques du Produit {$product->nom}.",
                        'file_path' => 'storage/docs/rcp_' . strtolower($product->code ?: 'prod') . '.pdf',
                        'version' => '1.0',
                        'is_active' => true,
                        'expiration_date' => now()->addYears(2),
                        'status' => 'approved',
                    ]
                );
            }
        }
    }
}
