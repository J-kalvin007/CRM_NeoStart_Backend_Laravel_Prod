<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{User, Country, Team, AccountType, ContactType, ProductType, Account, Contact, Category, Product, TourPlan, Activity, SampleMovement, Objectif, Campaign, KolProfile};
use Illuminate\Support\Str;
use Carbon\Carbon;

class FullTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        if (!$admin) {
            $this->command->error("Veuillez lancer DatabaseSeeder d'abord.");
            return;
        }

        $country = Country::first();

        $team = Team::first();

        // 1. Types de configuration (Agnostique)
        $hospitalType = AccountType::firstOrCreate(['code' => 'HOSP'], ['libelle' => 'Hôpital', 'is_active' => true]);
        $pharmacyType = AccountType::firstOrCreate(['code' => 'PHARM'], ['libelle' => 'Pharmacie', 'is_active' => true]);
        
        $doctorType = ContactType::firstOrCreate(['code' => 'MED'], [
            'libelle' => 'Médecin', 'is_active' => true,
            'validation_rules' => ['rpps' => 'required', 'specialite' => 'required']
        ]);

        $drugType = ProductType::firstOrCreate(['code' => 'DRUG'], ['libelle' => 'Médicament', 'is_stockable' => true, 'is_active' => true]);

        // 2. Création de Comptes (Accounts)
        $hospital = Account::firstOrCreate(['nom' => 'Hôpital Pitié Salpêtrière'], [
            'account_type_id' => $hospitalType->id,

            'user_id' => $admin->id,
            'email' => 'contact@pitie.fr',
            'telephone' => '0123456789',
            'adresse' => '83 Boulevard de l\'Hôpital, Paris',
            'gps_lat' => 48.838,
            'gps_lng' => 2.364,
            'statut' => 'actif',
            'meta' => ['capacite_lits' => 1500, 'secteur' => 'Public']
        ]);

        // 3. Création de Contacts
        $contact = Contact::firstOrCreate(['email' => 'jean.dupont@pitie.fr'], [
            'contact_type_id' => $doctorType->id,
            'account_id' => $hospital->id,
            'user_id' => $admin->id,
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'telephone' => '0600000000',
            'statut' => 'actif',
            'meta' => ['rpps' => '10001234567', 'specialite' => 'Cardiologie']
        ]);

        // 4. Catalogue : Categories & Products
        $cardioCat = Category::firstOrCreate(['nom' => 'Cardiologie'], ['slug' => 'cardiologie', 'is_active' => true]);
        $product = Product::firstOrCreate(['code' => 'CARDIO-100'], [
            'product_type_id' => $drugType->id,

            'nom' => 'CardioMax',
            'pght' => 12.50,
            'ppv' => 15.00,
            'devise' => 'EUR',
            'stock_actuel' => 1000,
            'statut' => 'actif'
        ]);

        // 5. Tour Plan & Activités (Visites)
        $tourPlan = TourPlan::firstOrCreate(['nom' => 'Tournée Septembre 2026'], [
            'user_id' => $admin->id,
            'date_debut' => Carbon::now()->startOfMonth(),
            'date_fin' => Carbon::now()->endOfMonth(),
            'statut' => 'en_cours'
        ]);

        $activity = Activity::firstOrCreate([
            'tour_plan_id' => $tourPlan->id,
            'contact_id' => $contact->id,
            'user_id' => $admin->id,
            'activity_type' => 'visite',
            'date_activite' => Carbon::now()->addDays(2),
            'statut' => 'planifiee',
            'notes' => 'Présentation CardioMax'
        ]);

        // 6. Objectif (Performance)
        Objectif::firstOrCreate(['description' => 'Objectif Visites Q3'], [
            'user_id' => $admin->id,
            'periode' => 'trimestre',
            'type_objectif' => 'nombre_visites',
            'valeur_cible' => 100,
            'valeur_realisee' => 25,
            'date_debut' => Carbon::now()->startOfQuarter(),
            'date_fin' => Carbon::now()->endOfQuarter(),
        ]);

        // 7. Campagne & KOL
        $campaign = Campaign::firstOrCreate(['nom' => 'Lancement CardioMax 2026'], [
            'country_id' => $country->id,
            'date_debut' => Carbon::now(),
            'date_fin' => Carbon::now()->addMonths(3),
            'budget' => 50000,
            'statut' => 'execution'
        ]);
        $campaign->products()->sync([$product->id]);
        $campaign->teams()->sync([$team->id]);

        KolProfile::firstOrCreate(['contact_id' => $contact->id], [
            'expertise' => 'Cardiologie, Hypertension',
            'influence_institution' => 'national',
            'engagement' => 'élevé',
            'publications' => 'Traitement moderne de l\'hypertension (2025)'
        ]);

        $this->command->info("Toutes les données de test (Agnotisques, Contacts, Activités, Campagnes, etc.) ont été insérées !");
    }
}
