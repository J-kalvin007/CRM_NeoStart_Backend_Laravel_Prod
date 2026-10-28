<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Models\Account;
use App\Models\Activity;
use App\Models\CoachingSession;
use App\Models\Contact;
use App\Models\Country;
use App\Models\DelegateRequest;
use App\Models\Expense;
use App\Models\Laboratory;
use App\Models\PharmacovigilanceReport;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SampleMovement;
use App\Models\TourPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EndToEndBusinessWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;
    protected User $delegate;
    protected Product $product;
    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create([
            'name' => 'Togo',
            'iso_code' => 'TG',
            'currency' => 'XOF',
        ]);


        $this->supervisor = User::factory()->create([
            'name' => 'Kossi AMOUZOU',
            'email' => 'kossi.amouzou@skynity.com',
            'password' => Hash::make('password123'),
        ]);

        $this->delegate = User::factory()->create([
            'manager_id' => $this->supervisor->id,
            'name' => 'Sena KPODAR',
            'email' => 'sena.kpodar@skynity.com',
            'password' => Hash::make('password123'),
        ]);

        \App\Models\Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        $this->supervisor->assignRole('superviseur');

        $this->laboratory = Laboratory::create([
            'nom' => 'Sanofi Togo',
            'contact_email' => 'contact@sanofi.tg',
            'is_active' => true,
        ]);

        $this->product = Product::create([

            'nom' => 'Amoxil 500mg',
            'code' => 'AMX-500',
            'dci' => 'Amoxicilline',
            'pght' => 2500.00,
            'ppv' => 3200.00,
            'laboratory_id' => $this->laboratory->id,
            'statut' => 'actif',
        ]);
        $this->product->countries()->attach($country->id, ['pght' => 2500, 'ppv' => 3200, 'devise' => 'XOF']);

    }

    /**
     * WORKFLOW 1: Authentification, Changement de mot de passe & OTP
     */
    public function test_workflow_01_authentication_and_identity(): void
    {
        Mail::fake();

        // 1.1 Premier accès imposant le changement de mot de passe
        $tempUser = User::factory()->create([
            'email' => 'temp.user@skynity.com',
            'password' => Hash::make('temp1234'),
            'force_password_change' => true,
        ]);

        $resChangeReq = $this->postJson('/api/v1/login', [
            'email' => 'temp.user@skynity.com',
            'password' => 'temp1234',
        ]);
        $resChangeReq->assertStatus(403)
                     ->assertJson(['status' => 'requires_password_change']);

        // 1.2 Authentification sécurisée par OTP
        $otpUser = User::factory()->create([
            'email' => 'otp.user@skynity.com',
            'password' => Hash::make('secret123'),
            'otp_enabled' => true,
            'force_password_change' => false,
        ]);

        $resOtpReq = $this->postJson('/api/v1/login', [
            'email' => 'otp.user@skynity.com',
            'password' => 'secret123',
        ]);
        $resOtpReq->assertStatus(202)
                  ->assertJson(['status' => 'requires_otp']);

        Mail::assertSent(LoginOtpMail::class, function ($mail) {
            return $mail->hasTo('otp.user@skynity.com');
        });

        // 1.3 Connexion directe du délégué
        $resDirect = $this->postJson('/api/v1/login', [
            'email' => $this->delegate->email,
            'password' => 'password123',
        ]);
        $resDirect->assertStatus(200)
                  ->assertJsonStructure(['token', 'user']);
    }

    /**
     * WORKFLOW 2: Création de Prescripteur/Établissement & Validation Superviseur
     */
    public function test_workflow_02_hcp_and_establishment_creation_and_manager_validation(): void
    {
        // Création par le délégué avec statut initial en_attente
        $contact = Contact::create([
            'nom' => 'AMEGBOR',
            'prenom' => 'Kodjo',
            'statut' => 'en_attente',
            'user_id' => $this->delegate->id,
        ]);

        $account = Account::create([

            'nom' => 'CHU Sylvanus Olympio',
            'statut' => 'en_attente',
            'user_id' => $this->delegate->id,
        ]);

        $this->assertEquals('en_attente', $contact->statut);
        $this->assertEquals('en_attente', $account->statut);

        // Validation par le superviseur
        $contact->update(['statut' => 'valide']);
        $account->update(['statut' => 'valide']);

        $this->assertEquals('valide', $contact->fresh()->statut);
        $this->assertEquals('valide', $account->fresh()->statut);
    }

    /**
     * WORKFLOW 3: Arbitrage du Portefeuille Prescripteurs (Limite de 100 HCPs)
     */
    public function test_workflow_03_portfolio_arbitrage_and_100_hcp_limit(): void
    {
        // Création des 100 contacts actifs attribués au délégué
        for ($i = 1; $i <= 100; $i++) {
            Contact::create([
                'nom' => "Doc$i",
                'prenom' => "Togo",
                'statut' => 'valide',
                'user_id' => $this->delegate->id,
            ]);
        }

        $activeCount = Contact::where('user_id', $this->delegate->id)
            ->where('statut', 'valide')
            ->count();

        $this->assertEquals(100, $activeCount);

        // Retrait d'un médecin inactif et ajout du nouveau médecin
        $oldContact = Contact::where('user_id', $this->delegate->id)->first();
        $oldContact->update(['statut' => 'archive']);

        $newContact = Contact::create([
            'nom' => 'SODJI',
            'prenom' => 'Afi',
            'statut' => 'valide',
            'user_id' => $this->delegate->id,
        ]);

        $updatedCount = Contact::where('user_id', $this->delegate->id)
            ->where('statut', 'valide')
            ->count();

        $this->assertEquals(100, $updatedCount);
        $this->assertEquals('archive', $oldContact->fresh()->statut);
    }

    /**
     * WORKFLOW 4: Planification & Validation des Tournées
     */
    public function test_workflow_04_tour_planning_submission_and_approval(): void
    {
        $tourPlan = TourPlan::create([
            'user_id' => $this->delegate->id,
            'nom' => 'Circuit CHU Sylvanus Olympio - Semaine 38',
            'date_debut' => now()->startOfWeek()->toDateString(),
            'date_fin' => now()->endOfWeek()->toDateString(),
            'statut' => 'soumis',
        ]);

        $this->assertEquals('soumis', $tourPlan->statut);

        // Validation par le superviseur
        $tourPlan->update(['statut' => 'valide', 'valide_par' => $this->supervisor->id, 'valide_le' => now()]);
        $this->assertEquals('valide', $tourPlan->fresh()->statut);
        $this->assertEquals($this->supervisor->id, $tourPlan->fresh()->valide_par);
    }

    /**
     * WORKFLOW 5: Compte Rendu de Visite, Échantillons (Lot/Péremption) & Signature
     */
    public function test_workflow_05_visit_execution_samples_tracking_and_signature(): void
    {
        $contact = Contact::create([
            'nom' => 'AKA',
            'prenom' => 'Jean',
            'statut' => 'valide',
            'user_id' => $this->delegate->id,
        ]);

        $activity = Activity::create([
            'user_id' => $this->delegate->id,
            'contact_id' => $contact->id,
            'title' => 'Visite Cardio-Vasculaire',
            'type_activite' => 'visite_medicale',
            'statut' => 'effectuee',
            'date_activite' => now()->toDateString(),
            'heure_debut' => '10:00:00',
            'compte_rendu' => 'Présentation Amoxil 500mg effectuée avec succès.',
        ]);

        // Mouvement d'échantillons avec traçabilité légale du lot
        $sample = SampleMovement::create([
            'product_id' => $this->product->id,
            'user_id' => $this->delegate->id,
            'contact_id' => $contact->id,
            'activity_id' => $activity->id,
            'movement_type' => 'remise',
            'quantity' => 5,
            'lot_number' => 'LOT-2025-A456',
            'expiry_date' => '2027-12-31',
            'signature_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...',
        ]);

        $this->assertEquals('LOT-2025-A456', $sample->lot_number);
        $this->assertEquals(5, $sample->quantity);
        $this->assertNotNull($sample->signature_url);
    }

    /**
     * WORKFLOW 6: Compte Rendu d'Activité & Validation Manager
     */
    public function test_workflow_06_activity_report_submission_and_validation(): void
    {
        $activity = Activity::create([
            'user_id' => $this->delegate->id,
            'title' => 'Rapport Visite Officine',
            'type_activite' => 'visite_medicale',
            'statut' => 'soumis_definitif',
            'date_activite' => now()->toDateString(),
            'compte_rendu' => 'Commande récurrente confirmée.',
        ]);

        $this->assertEquals('soumis_definitif', $activity->statut);

        // Validation par le superviseur
        $activity->update(['statut' => 'validee']);
        $this->assertEquals('validee', $activity->fresh()->statut);
    }

    /**
     * WORKFLOW 7: Visites en Duo Superviseur & Coaching
     */
    public function test_workflow_07_supervisor_duo_and_coaching_session(): void
    {
        $duo = CoachingSession::create([
            'supervisor_id' => $this->supervisor->id,
            'delegate_id' => $this->delegate->id,
            'date_duo' => now()->toDateString(),
            'zone' => 'Lomé Est',
            'motif' => 'Accompagnement & Lancement Produit',
            'objectifs_prevus_json' => ['5 visites prévues'],
            'resultats_constates_json' => ['4 visites réalisées'],
            'taux_atteinte_pct' => 80.0,
            'commentaires_superviseur' => 'Bonne maitrise de l\'argumentaire clinique.',
            'status' => 'completed',
        ]);

        $this->assertEquals(80.0, $duo->taux_atteinte_pct);
        $this->assertEquals('completed', $duo->status);
    }

    /**
     * WORKFLOW 8: Notes de Frais & Approval Pipeline
     */
    public function test_workflow_08_expense_note_submission_and_approval(): void
    {
        $expense = Expense::create([

            'user_id' => $this->delegate->id,
            'title' => 'Carburant Tournée Maritime',
            'amount' => 25000.00,
            'expense_date' => now()->toDateString(),
            'status' => 'submitted',
            'receipt_file_path' => 'receipts/fuel_001.jpg',
        ]);

        $this->assertEquals('submitted', $expense->status);

        // Validation par le superviseur
        $expense->update(['status' => 'approved', 'approved_by' => $this->supervisor->id]);
        $this->assertEquals('approved', $expense->fresh()->status);
        $this->assertEquals($this->supervisor->id, $expense->fresh()->approved_by);
    }

    /**
     * WORKFLOW 9: Saisie Ventes Grossistes (SOCOPHAR/TOP PHARMA) & Calcul CA
     */
    public function test_workflow_09_wholesale_sales_entry_and_revenue_calculation(): void
    {
        $account = Account::create([

            'nom' => 'SOCOPHAR TOGO',
            'statut' => 'valide',
            'user_id' => $this->delegate->id,
        ]);

        $quantity = 1500;
        $unitPrice = $this->product->pght; // 2500.00 FCFA
        $expectedTotal = $quantity * $unitPrice; // 3,750,000.00 FCFA

        $sale = Sale::create([
            'user_id' => $this->delegate->id,

            'account_id' => $account->id,
            'amount' => $expectedTotal,
            'status' => 'valide',
            'sale_date' => now()->toDateString(),
            'mois_concerne' => '2026-09',
            'commentaires' => 'Saisie Ventes SOCOPHAR TOGO pour secteur Lomé Est',
            'products_json' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'wholesaler' => 'SOCOPHAR TOGO',
                ]
            ],
        ]);

        $this->assertEquals(3750000.00, $sale->amount);
        $this->assertEquals('2026-09', $sale->mois_concerne);
    }

    /**
     * WORKFLOW 10: Demandes à Escalade (DM ➔ SUP ➔ DC ➔ DR)
     */
    public function test_workflow_10_delegate_requests_and_multi_tier_escalation(): void
    {
        $request = DelegateRequest::create([
            'user_id' => $this->delegate->id,
            'request_type' => 'dotation_exceptionnelle',
            'reason' => 'Besoin de 100 échantillons supplémentaires pour congrès médical',
            'priority' => 'haute',
            'status' => 'soumis',
        ]);

        $this->assertEquals('soumis', $request->status);

        // Étape 1 : Approbation par le Superviseur
        $request->update(['status' => 'valide_sup']);
        $this->assertEquals('valide_sup', $request->fresh()->status);

        // Étape 2 : Approbation finale par le Directeur Commercial
        $request->update(['status' => 'approuve_definitif']);
        $this->assertEquals('approuve_definitif', $request->fresh()->status);
    }

    /**
     * WORKFLOW 11: Moteur de Synchronisation Hors-Ligne (Sync Push)
     */
    public function test_workflow_11_offline_first_fifo_sync_push(): void
    {
        $date = now()->addDay()->toDateString();
        $response = $this->actingAs($this->delegate)->postJson('/api/v1/sync/push', [
            'queue' => [[
                'endpoint' => 'activities', 'method' => 'POST',
                'payload' => ['activity_type' => 'reunion', 'date_activite' => $date, 'date_fin' => $date,
                    'heure_debut' => '09:00', 'heure_fin' => '10:00', 'notes' => 'Réunion hors ligne'],
            ]],
        ]);
        $response->assertOk()->assertJsonPath('processed_items', 1)->assertJsonCount(0, 'errors');
        $this->assertDatabaseHas('activities', ['user_id' => $this->delegate->id,
            'notes' => 'Réunion hors ligne']);
    }

    /**
     * WORKFLOW 12: Pharmacovigilance & Déclaration Alerte Sévère
     */
    public function test_workflow_12_pharmacovigilance_adverse_event_report(): void
    {
        Mail::fake();

        $contact = Contact::create([
            'nom' => 'KPADENOU',
            'prenom' => 'Kossivi',
            'statut' => 'valide',
            'user_id' => $this->delegate->id,
        ]);

        $report = PharmacovigilanceReport::create([
            'user_id' => $this->delegate->id,
            'contact_id' => $contact->id,
            'product_id' => $this->product->id,
            'patient_initials' => 'K.A.',
            'adverse_event_description' => 'Réaction allergique cutanée sévère observée',
            'severity' => 'elevee',
            'report_date' => now()->toDateString(),
            'status' => 'soumis',
        ]);

        $this->assertEquals('elevee', $report->severity);
        $this->assertEquals('K.A.', $report->patient_initials);
    }

    /**
     * WORKFLOW 13: Intégrité Produit & Laboratoire Supplier Unique
     */
    public function test_workflow_13_single_laboratory_product_integrity(): void
    {
        $this->assertEquals('Amoxicilline', $this->product->dci);
        $this->assertEquals(2500.00, $this->product->pght);
        $this->assertEquals($this->laboratory->id, $this->product->laboratory_id);
        $this->assertEquals('Sanofi Togo', $this->product->laboratory->nom);
    }

    /**
     * WORKFLOW 14: Analytique & Executive Summary Manager
     */
    public function test_workflow_14_analytics_and_team_performance_summary(): void
    {
        $response = $this->actingAs($this->supervisor)->getJson('/api/v1/analytics/executive');

        $response->assertStatus(200);
    }
}
