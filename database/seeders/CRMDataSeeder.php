<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\AccountType;
use App\Models\ContactType;
use App\Models\Account;
use App\Models\Contact;
use App\Models\User;
use App\Models\Team;
use Illuminate\Support\Facades\Hash;

class CRMDataSeeder extends Seeder
{
    public function run(): void
    {

        $team = Team::with('sector.zone.territory.region')->first();

        $sector = $team->sector ?? null;
        $zone = $sector->zone ?? null;
        $territory = $zone->territory ?? null;
        $region = $territory->region ?? null;

        // 1. Délégué principal
        $delegue = User::where('email', 'adommoucha@gmail.com')->first();
        if (!$delegue) {
            $delegue = User::firstOrCreate([
                'email' => 'delegue@crm.local'
            ], [
                'name' => 'Alice Déléguée',
                'password' => Hash::make('password'),
                'country_id' => 1,
                'region_id' => $region->id ?? null,
                'territory_id' => $territory->id ?? null,
                'zone_id' => $zone->id ?? null,
                'sector_id' => $sector->id ?? null,
                'team_id' => $team->id ?? null,
                'status' => true,
            ]);
        }
        if ($delegue && !$delegue->hasRole('delegue_medical')) {
            $delegue->assignRole('delegue_medical');
        }

        // 2. Types
        $hopitalType = AccountType::firstOrCreate(['code' => 'HOP'], ['libelle' => 'Hôpital']);
        $pharmaType = AccountType::firstOrCreate(['code' => 'PHA'], ['libelle' => 'Pharmacie']);
        $cliniqueType = AccountType::firstOrCreate(['code' => 'CLI'], ['libelle' => 'Clinique']);

        $medecinType = ContactType::firstOrCreate(['code' => 'MED'], ['libelle' => 'Médecin']);

        // 3. Comptes RÉELS (Établissements de Santé Abidjan)
        $realAccounts = [
            ['nom' => 'Polyclinique Sainte Anne', 'type' => $cliniqueType, 'ville' => 'Abidjan (Cocody)'],
            ['nom' => 'Clinique Internationale Sainte Marie', 'type' => $cliniqueType, 'ville' => 'Abidjan (Riviera)'],
            ['nom' => 'CHU de Cocody', 'type' => $hopitalType, 'ville' => 'Abidjan (Cocody)'],
            ['nom' => 'Hôpital Militaire d\'Abidjan', 'type' => $hopitalType, 'ville' => 'Abidjan (Port-Bouët)'],
            ['nom' => 'Centre Médical de la Riviera', 'type' => $cliniqueType, 'ville' => 'Abidjan (Riviera)'],
            ['nom' => 'Pharmacie Centrale de Marcory', 'type' => $pharmaType, 'ville' => 'Abidjan (Marcory)'],
            ['nom' => 'Clinique Médicale du Plateau', 'type' => $cliniqueType, 'ville' => 'Abidjan (Plateau)'],
            ['nom' => 'Hôpital Général de Treichville', 'type' => $hopitalType, 'ville' => 'Abidjan (Treichville)'],
        ];

        $createdAccounts = [];
        foreach ($realAccounts as $accData) {
            $acc = Account::firstOrCreate([
                'nom' => $accData['nom']
            ], [
                'user_id' => $delegue->id,
                'account_type_id' => $accData['type']->id,
                'ville' => $accData['ville'],
                'statut' => 'actif',
            ]);
            $createdAccounts[] = $acc;
        }

        // 4. Contacts RÉELS (Praticiens Médicaux)
        $realDoctors = [
            ['prenom' => 'Marc', 'nom' => 'Adjanohoun', 'titre' => 'Dr.', 'specialite' => 'Cardiologie', 'email' => 'marc.adjanohoun@sante.ci'],
            ['prenom' => 'Sarah', 'nom' => 'Kouassi', 'titre' => 'Dr.', 'specialite' => 'Pédiatrie', 'email' => 'sarah.kouassi@sante.ci'],
            ['prenom' => 'Jean-Baptiste', 'nom' => 'Koffi', 'titre' => 'Dr.', 'specialite' => 'Médecine Générale', 'email' => 'jb.koffi@sante.ci'],
            ['prenom' => 'Marie-Claire', 'nom' => 'Diallo', 'titre' => 'Dr.', 'specialite' => 'Gynécologie', 'email' => 'mc.diallo@sante.ci'],
            ['prenom' => 'Ibrahim', 'nom' => 'Touré', 'titre' => 'Dr.', 'specialite' => 'Neurologie', 'email' => 'ibrahim.toure@sante.ci'],
            ['prenom' => 'Eric', 'nom' => 'Mensah', 'titre' => 'Dr.', 'specialite' => 'Dermatologie', 'email' => 'eric.mensah@sante.ci'],
            ['prenom' => 'Patricia', 'nom' => 'N\'Guessan', 'titre' => 'Dr.', 'specialite' => 'Ophtalmologie', 'email' => 'patricia.nguessan@sante.ci'],
            ['prenom' => 'Alain', 'nom' => 'Yao', 'titre' => 'Dr.', 'specialite' => 'Rhumatologie', 'email' => 'alain.yao@sante.ci'],
            ['prenom' => 'Estelle', 'nom' => 'Aka', 'titre' => 'Dr.', 'specialite' => 'Cardiologie', 'email' => 'estelle.aka@sante.ci'],
            ['prenom' => 'Philippe', 'nom' => 'Bamba', 'titre' => 'Dr.', 'specialite' => 'Gastro-entérologie', 'email' => 'philippe.bamba@sante.ci'],
            ['prenom' => 'Sandrine', 'nom' => 'Konan', 'titre' => 'Dr.', 'specialite' => 'Endocrinologie', 'email' => 'sandrine.konan@sante.ci'],
            ['prenom' => 'Yves', 'nom' => 'Brou', 'titre' => 'Dr.', 'specialite' => 'Pneumologie', 'email' => 'yves.brou@sante.ci'],
        ];

        foreach ($realDoctors as $index => $doc) {
            $account = $createdAccounts[$index % count($createdAccounts)] ?? null;
            Contact::firstOrCreate([
                'email' => $doc['email']
            ], [
                'account_id' => $account?->id,
                'user_id' => $delegue->id,
                'contact_type_id' => $medecinType->id,
                'nom' => $doc['nom'],
                'prenom' => $doc['prenom'],
                'titre' => $doc['titre'],
                'statut' => 'actif',
                'score' => 10,
                'potentiel' => 'fort',
                'meta' => ['specialite' => $doc['specialite']],
            ]);
        }

        // 5. Prescripteurs et Établissements EN ATTENTE de validation pour tester l'onglet Manager
        Contact::firstOrCreate([
            'email' => 'christian.tanoh@sante.ci'
        ], [
            'account_id' => $createdAccounts[0]->id ?? null,
            'user_id' => $delegue->id,
            'contact_type_id' => $medecinType->id,
            'nom' => 'Tanoh',
            'prenom' => 'Christian',
            'titre' => 'Dr.',
            'statut' => 'en_attente',
            'score' => 5,
            'potentiel' => 'moyen',
            'meta' => ['specialite' => 'Chirurgie'],
        ]);

        Contact::firstOrCreate([
            'email' => 'aicha.diop@sante.ci'
        ], [
            'account_id' => $createdAccounts[1]->id ?? null,
            'user_id' => $delegue->id,
            'contact_type_id' => $medecinType->id,
            'nom' => 'Diop',
            'prenom' => 'Aïcha',
            'titre' => 'Dr.',
            'statut' => 'en_attente',
            'score' => 8,
            'potentiel' => 'fort',
            'meta' => ['specialite' => 'Pédiatrie'],
        ]);

        Account::firstOrCreate([
            'nom' => 'Polyclinique Internationale Pasteur'
        ], [

            'user_id' => $delegue->id,
            'account_type_id' => $cliniqueType->id,
            'ville' => 'Abidjan (Marcory)',
            'statut' => 'en_attente',
        ]);

        // 6. Plan de Tournée SOUMIS pour validation manager
        \App\Models\TourPlan::firstOrCreate([
            'nom' => 'Planning Semaine - Secteur Abidjan Cocody'
        ], [
            'user_id' => $delegue->id,
            'date_debut' => now()->startOfWeek()->toDateString(),
            'date_fin' => now()->endOfWeek()->toDateString(),
            'description' => 'Couverture ciblée des spécialistes en cardiologie et pédiatrie',
            'statut' => 'soumis',
        ]);

        // 7. Stock d'échantillons initial pour le délégué
        $allProducts = \App\Models\Product::all();
        foreach ($allProducts as $idx => $prod) {
            \App\Models\SampleStock::firstOrCreate([
                'user_id' => $delegue->id,
                'product_id' => $prod->id,
                'lot_number' => 'LOT-2026-' . strtoupper(substr(md5($prod->code ?? $prod->id), 0, 5)),
            ], [
                'expiry_date' => now()->addYears(2)->toDateString(),
                'quantity' => 50 + ($idx * 10),
            ]);
        }

        // 8. Notifications initiales In-App
        $manager = User::where('email', 'cmoucharif@gmail.com')->first();
        if ($manager) {
            \App\Http\Controllers\Api\V1\NotificationController::sendNotification(
                $manager,
                'Nouveau prescripteur en attente',
                'Le délégué ' . $delegue->name . ' a soumis le Dr. Christian Tanoh pour validation.',
                'validation',
                'manager'
            );
            \App\Http\Controllers\Api\V1\NotificationController::sendNotification(
                $manager,
                'Nouveau planning soumis',
                'Le délégué ' . $delegue->name . ' a soumis son planning de tournée pour la semaine.',
                'planning',
                'manager'
            );
        }

        \App\Http\Controllers\Api\V1\NotificationController::sendNotification(
            $delegue,
            'Dotation de stock d\'échantillons',
            'Votre stock d\'échantillons pour le mois a été crédité.',
            'stock',
            'delegate'
        );
    }
}
