<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AccountType;

class AccountTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'HOP', 'libelle' => 'Hôpital', 'description' => 'Hôpital public ou centre hospitalier universitaire', 'is_active' => true, 'statut' => 'actif'],
            ['code' => 'CLI', 'libelle' => 'Clinique', 'description' => 'Clinique privée ou centre de soins privé', 'is_active' => true, 'statut' => 'actif'],
            ['code' => 'PHA', 'libelle' => 'Pharmacie', 'description' => 'Pharmacie d\'officine', 'is_active' => true, 'statut' => 'actif'],
            ['code' => 'OFF', 'libelle' => 'Officine', 'description' => 'Officine pharmaceutique', 'is_active' => true, 'statut' => 'actif'],
            ['code' => 'GRO', 'libelle' => 'Grossiste', 'description' => 'Grossiste répartiteur pharmaceutique', 'is_active' => true, 'statut' => 'actif'],
            ['code' => 'CAB', 'libelle' => 'Cabinet Médical', 'description' => 'Cabinet médical privé ou groupe de praticiens', 'is_active' => true, 'statut' => 'actif'],
            ['code' => 'OTH', 'libelle' => 'Autre', 'description' => 'Autre type d\'établissement', 'is_active' => true, 'statut' => 'actif'],
        ];

        foreach ($types as $type) {
            AccountType::firstOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
