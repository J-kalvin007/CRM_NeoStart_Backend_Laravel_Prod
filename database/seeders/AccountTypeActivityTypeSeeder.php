<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AccountType;
use App\Models\ActivityType;

class AccountTypeActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure basic activity types exist
        $activityTypes = [
            'one_to_one'  => ActivityType::firstOrCreate(['code' => 'one_to_one'], ['name' => 'One to One', 'description' => 'Visite médicale individuelle']),
            'staff'       => ActivityType::firstOrCreate(['code' => 'staff'], ['name' => 'STAFF', 'description' => 'Staff hospitalier réunissant plusieurs professionnels']),
            'rp'          => ActivityType::firstOrCreate(['code' => 'rp'], ['name' => 'RP', 'description' => 'Événement et relations publiques']),
            'negociation' => ActivityType::firstOrCreate(['code' => 'negociation'], ['name' => 'Négociation de commande', 'description' => 'Négociation commerciale et commande']),
        ];

        // 2. Link with Account Types
        $accountTypes = AccountType::all();
        foreach ($accountTypes as $accountType) {
            $code = strtolower($accountType->code);
            $libelle = strtolower($accountType->libelle);

            if (str_contains($code, 'pha') || str_contains($libelle, 'pharmacie') || str_contains($code, 'off')) {
                // Pharmacie : Négociation de commande, One to One, RP
                $accountType->activityTypes()->syncWithoutDetaching([
                    $activityTypes['negociation']->id,
                    $activityTypes['one_to_one']->id,
                    $activityTypes['rp']->id,
                ]);
            } elseif (str_contains($code, 'cli') || str_contains($libelle, 'clinique')) {
                // Clinique : Négociation de commande, One to One, RP, Staff
                $accountType->activityTypes()->syncWithoutDetaching([
                    $activityTypes['negociation']->id,
                    $activityTypes['one_to_one']->id,
                    $activityTypes['rp']->id,
                    $activityTypes['staff']->id,
                ]);
            } elseif (str_contains($code, 'hop') || str_contains($code, 'hosp') || str_contains($libelle, 'hôpital') || str_contains($libelle, 'hopital')) {
                // Hôpital : Staff, Négociation de commande, One to One, RP
                $accountType->activityTypes()->syncWithoutDetaching([
                    $activityTypes['staff']->id,
                    $activityTypes['negociation']->id,
                    $activityTypes['one_to_one']->id,
                    $activityTypes['rp']->id,
                ]);
            } else {
                // Par défaut : Tous les types
                $accountType->activityTypes()->syncWithoutDetaching(collect($activityTypes)->pluck('id')->toArray());
            }
        }
    }
}
