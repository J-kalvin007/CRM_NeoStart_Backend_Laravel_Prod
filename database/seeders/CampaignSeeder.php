<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Campaign;
use App\Models\Country;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $country = Country::first();

        if ($country) {
            Campaign::firstOrCreate([
                'nom' => 'Campagne Lancement CardioProtect Q3 2026'
            ], [
                'country_id' => $country->id,
                'date_debut' => now()->startOfQuarter()->toDateString(),
                'date_fin' => now()->endOfQuarter()->toDateString(),
                'cible' => 'Cardiologues et Pharmacies',
                'message' => 'Sensibilisation et positionnement de CardioProtect.',
                'budget' => 5000000.00,
                'statut' => 'deploiement'
            ]);
        }
    }
}
