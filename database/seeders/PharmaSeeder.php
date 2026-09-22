<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Laboratory;
use App\Models\TherapeuticClass;

class PharmaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Création des Laboratoires principaux
        $laboratories = [
            ['nom' => 'Sanofi', 'contact_email' => 'contact@sanofi.fr', 'is_active' => true],
            ['nom' => 'Pfizer', 'contact_email' => 'contact@pfizer.fr', 'is_active' => true],
            ['nom' => 'AstraZeneca', 'contact_email' => 'contact@astrazeneca.fr', 'is_active' => true],
            ['nom' => 'Novartis', 'contact_email' => 'contact@novartis.fr', 'is_active' => true],
            ['nom' => 'GSK', 'contact_email' => 'contact@gsk.fr', 'is_active' => true],
            ['nom' => 'Roche', 'contact_email' => 'contact@roche.fr', 'is_active' => true],
            ['nom' => 'Johnson & Johnson', 'contact_email' => 'contact@jnj.fr', 'is_active' => true],
            ['nom' => 'Merck', 'contact_email' => 'contact@merck.fr', 'is_active' => true],
        ];

        foreach ($laboratories as $lab) {
            Laboratory::firstOrCreate(['nom' => $lab['nom']], $lab);
        }

        // Création des Classes Thérapeutiques principales
        $classes = [
            ['nom' => 'Antibiotiques', 'description' => 'Médicaments luttant contre les infections bactériennes', 'is_active' => true],
            ['nom' => 'Antalgiques', 'description' => 'Médicaments destinés à réduire la douleur', 'is_active' => true],
            ['nom' => 'Anti-inflammatoires', 'description' => 'Médicaments luttant contre l\'inflammation', 'is_active' => true],
            ['nom' => 'Anticoagulants', 'description' => 'Médicaments empêchant la coagulation du sang', 'is_active' => true],
            ['nom' => 'Antihistaminiques', 'description' => 'Médicaments luttant contre les allergies', 'is_active' => true],
            ['nom' => 'Antidépresseurs', 'description' => 'Médicaments traitant la dépression', 'is_active' => true],
            ['nom' => 'Antidiabétiques', 'description' => 'Médicaments traitant le diabète', 'is_active' => true],
            ['nom' => 'Antihypertenseurs', 'description' => 'Médicaments traitant l\'hypertension artérielle', 'is_active' => true],
        ];

        foreach ($classes as $class) {
            TherapeuticClass::firstOrCreate(['nom' => $class['nom']], $class);
        }
    }
}
