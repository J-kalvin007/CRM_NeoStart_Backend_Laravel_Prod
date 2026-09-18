<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subsidiary;
use App\Models\AccountType;
use App\Models\ContactType;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Activity;
use App\Models\User;
use App\Models\Team;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class CRMDataSeeder extends Seeder
{
    public function run(): void
    {
        $subsidiary = Subsidiary::where('code', 'SUB-FR')->first();
        $team = Team::with('sector.zone.territory.region')->first();

        $sector = $team->sector ?? null;
        $zone = $sector->zone ?? null;
        $territory = $zone->territory ?? null;
        $region = $territory->region ?? null;

        // 1. Création d'un Délégué de test
        $delegue = User::firstOrCreate([
            'email' => 'delegue@crm.local'
        ], [
            'name' => 'Alice Déléguée',
            'password' => Hash::make('password'),
            'country_id' => $subsidiary->country_id ?? null,
            'subsidiary_id' => $subsidiary->id ?? null,
            'region_id' => $region->id ?? null,
            'territory_id' => $territory->id ?? null,
            'zone_id' => $zone->id ?? null,
            'sector_id' => $sector->id ?? null,
            'team_id' => $team->id ?? null,
            'status' => true,
        ]);
        if (!$delegue->hasRole('delegue_medical')) {
            $delegue->assignRole('delegue_medical');
        }

        // 2. Création des Types
        $hopitalType = AccountType::firstOrCreate(['code' => 'HOP'], ['libelle' => 'Hôpital']);
        $pharmaType = AccountType::firstOrCreate(['code' => 'PHA'], ['libelle' => 'Pharmacie']);
        
        $medecinType = ContactType::firstOrCreate(['code' => 'MED'], ['libelle' => 'Médecin']);

        // 3. Création des Comptes (Accounts)
        if ($subsidiary) {
            for ($i = 1; $i <= 5; $i++) {
                Account::firstOrCreate([
                    'nom' => "Établissement Santé $i"
                ], [
                    'subsidiary_id' => $subsidiary->id,
                    'user_id' => $delegue->id,
                    'account_type_id' => $i % 2 == 0 ? $hopitalType->id : $pharmaType->id,
                    'ville' => 'Paris',
                    'statut' => 'actif',
                ]);
            }
        }

        // 4. Création des Contacts
        $accounts = Account::all();
        foreach ($accounts as $index => $account) {
            for ($j = 1; $j <= 3; $j++) {
                Contact::firstOrCreate([
                    'email' => "medecin{$index}_{$j}@hopital.com"
                ], [
                    'account_id' => $account->id,
                    'user_id' => $delegue->id,
                    'contact_type_id' => $medecinType->id,
                    'nom' => "NomMedecin{$index}_{$j}",
                    'prenom' => "Prenom_{$j}",
                    'titre' => 'Dr.',
                    'statut' => 'actif',
                    'potentiel' => 'faible', // Sera calculé par la suite
                    'score' => 10,
                    'meta' => ['specialite' => 'Cardiologie'],
                ]);
            }
        }

        // 5. Création de visites clôturées pour tester le Scoring
        $contacts = Contact::take(5)->get();
        foreach ($contacts as $index => $contact) {
            // Le contact 0 aura 3 visites (Fort)
            // Le contact 1 aura 1 visite (Moyen)
            // Les autres 0 visites (Faible)
            
            $visitesCount = 0;
            if ($index == 0) $visitesCount = 3;
            if ($index == 1) $visitesCount = 1;

            for ($v = 0; $v < $visitesCount; $v++) {
                Activity::create([
                    'user_id' => $delegue->id,
                    'contact_id' => $contact->id,
                    'account_id' => $contact->account_id,
                    'activity_type' => 'visite',
                    'date_activite' => Carbon::now()->subDays(rand(1, 25))->toDateString(),
                    'heure_debut' => '09:00',
                    'heure_fin' => '09:30',
                    'statut' => 'realisee',
                ]);
            }
        }
    }
}
