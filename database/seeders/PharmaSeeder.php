<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Laboratory;
use App\Models\TherapeuticClass;
use App\Models\Product;
use App\Models\Subsidiary;
use App\Models\ProductType;

class PharmaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subsidiary = Subsidiary::first();

        // 1. Création des Laboratoires principaux
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

        $labsMap = [];
        foreach ($laboratories as $lab) {
            $labsMap[$lab['nom']] = Laboratory::firstOrCreate(['nom' => $lab['nom']], $lab);
        }

        // 2. Création des Classes Thérapeutiques principales
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

        $classesMap = [];
        foreach ($classes as $class) {
            $classesMap[$class['nom']] = TherapeuticClass::firstOrCreate(['nom' => $class['nom']], $class);
        }

        $prodType = ProductType::firstOrCreate(['code' => 'MED'], ['libelle' => 'Médicament']);

        // 3. Création des Produits Médicament
        $products = [
            ['nom' => 'Amoxicilline 500mg', 'code' => 'AMX-500', 'dci' => 'Amoxicilline', 'lab' => 'Sanofi', 'class' => 'Antibiotiques', 'cond' => 'Boîte de 16 gélules'],
            ['nom' => 'Doliprane 1000mg', 'code' => 'DOL-1000', 'dci' => 'Paracétamol', 'lab' => 'Sanofi', 'class' => 'Antalgiques', 'cond' => 'Boîte de 8 comprimés'],
            ['nom' => 'Augmentin 1g', 'code' => 'AUG-1000', 'dci' => 'Amoxicilline + Acide Clavulanique', 'lab' => 'GSK', 'class' => 'Antibiotiques', 'cond' => 'Boîte de 14 sachets'],
            ['nom' => 'Oméprazole 20mg', 'code' => 'OMP-020', 'dci' => 'Oméprazole', 'lab' => 'AstraZeneca', 'class' => 'Anti-inflammatoires', 'cond' => 'Boîte de 28 gélules'],
            ['nom' => 'Metformine 850mg', 'code' => 'MET-850', 'dci' => 'Metformine', 'lab' => 'Merck', 'class' => 'Antidiabétiques', 'cond' => 'Boîte de 30 comprimés'],
            ['nom' => 'Amlodipine 5mg', 'code' => 'AML-005', 'dci' => 'Amlodipine', 'lab' => 'Pfizer', 'class' => 'Antihypertenseurs', 'cond' => 'Boîte de 30 comprimés'],
            ['nom' => 'Spasfon 80mg', 'code' => 'SPS-080', 'dci' => 'Phloroglucinol', 'lab' => 'Sanofi', 'class' => 'Antalgiques', 'cond' => 'Boîte de 30 comprimés'],
            ['nom' => 'Ventoline 100µg', 'code' => 'VNT-100', 'dci' => 'Salbutamol', 'lab' => 'GSK', 'class' => 'Anti-inflammatoires', 'cond' => 'Inhalateur 200 doses'],
            ['nom' => 'Solupred 20mg', 'code' => 'SLP-020', 'dci' => 'Prednisolone', 'lab' => 'Sanofi', 'class' => 'Anti-inflammatoires', 'cond' => 'Boîte de 20 comprimés effervescents'],
            ['nom' => 'Cefaclor 500mg', 'code' => 'CEF-500', 'dci' => 'Cefaclor', 'lab' => 'Novartis', 'class' => 'Antibiotiques', 'cond' => 'Boîte de 12 gélules'],
        ];

        if ($subsidiary) {
            foreach ($products as $p) {
                Product::firstOrCreate([
                    'code' => $p['code']
                ], [
                    'subsidiary_id' => $subsidiary->id,
                    'product_type_id' => $prodType->id,
                    'laboratory_id' => $labsMap[$p['lab']]->id ?? null,
                    'therapeutic_class_id' => $classesMap[$p['class']]->id ?? null,
                    'nom' => $p['nom'],
                    'dci' => $p['dci'],
                    'conditionnement' => $p['cond'],
                    'statut' => 'actif',
                    'stock_actuel' => 500,
                    'stock_alerte' => 50,
                ]);
            }
        }
    }
}
