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
        $docs = [
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'documentable_id' => \Illuminate\Support\Str::uuid(),
                'documentable_type' => 'App\\Models\\Product',
                'title' => 'Brochure Produit X - 2026',
                'type' => 'pdf',
                'file_path' => '/storage/docs/brochure_x.pdf',
                'version' => '1.2',
                'is_active' => true,
                'expiration_date' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'documentable_id' => \Illuminate\Support\Str::uuid(),
                'documentable_type' => 'App\\Models\\Product',
                'title' => 'E-Detailing Cardio Pro - Tablette',
                'type' => 'presentation',
                'file_path' => '/storage/docs/cardio_pro.zip',
                'version' => '2.0',
                'is_active' => true,
                'expiration_date' => now()->addMonths(6),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'documentable_id' => \Illuminate\Support\Str::uuid(),
                'documentable_type' => 'App\\Models\\Product',
                'title' => 'Posologie et Contre-indications (Ancien)',
                'type' => 'pdf',
                'file_path' => '/storage/docs/poso_old.pdf',
                'version' => '1.0',
                'is_active' => true,
                'expiration_date' => now()->subDays(10), // EXPIRE
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'documentable_id' => \Illuminate\Support\Str::uuid(),
                'documentable_type' => 'App\\Models\\Event',
                'title' => 'Vidéo de Lancement - Q1 2026',
                'type' => 'video',
                'file_path' => '/storage/docs/launch.mp4',
                'version' => '1.0',
                'is_active' => true,
                'expiration_date' => now()->addMonths(3),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        \Illuminate\Support\Facades\DB::table('documents')->insert($docs);
    }
}
