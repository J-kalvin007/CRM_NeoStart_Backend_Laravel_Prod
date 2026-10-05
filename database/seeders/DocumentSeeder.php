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
                $filePath = 'docs/presentation_' . strtolower($product->code ?: 'prod') . '.pdf';
                $title = "E-Detailing {$product->nom} — Présentation Tablette";

                if (str_contains(strtolower($product->nom), 'amoxicilline')) {
                    $filePath = 'docs/soutenance_amoxicilline.pptx';
                    $title = 'Présentation e-Detailing Soutenance Amoxicilline 500mg';
                } elseif (str_contains(strtolower($product->nom), 'augmentin')) {
                    $filePath = 'docs/compte_rendu_augmentin.pdf';
                    $title = 'Compte-rendu & Notice Visite Augmentin 1g';
                }

                \App\Models\Document::updateOrCreate(
                    [
                        'documentable_id' => $product->id,
                        'documentable_type' => 'App\\Models\\Product',
                        'type' => 'presentation',
                    ],
                    [
                        'title' => $title,
                        'description' => "Support e-Detailing officiel pour la présentation de {$product->nom}.",
                        'file_path' => $filePath,
                        'version' => '2.0',
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
                        'file_path' => 'docs/rcp_' . strtolower($product->code ?: 'prod') . '.pdf',
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
