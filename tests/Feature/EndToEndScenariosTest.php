<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Activity;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Sanctum\Sanctum;

class EndToEndScenariosTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesManager;
    protected User $supervisor;
    protected User $delegate;
    protected Account $accountA;
    protected Account $accountB;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'sales_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'delegue_medical', 'guard_name' => 'web']);

        $this->salesManager = User::factory()->create();
        $this->salesManager->assignRole('sales_manager');

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('superviseur');

        $this->delegate = User::factory()->create(['manager_id' => $this->supervisor->id]);
        $this->delegate->assignRole('delegue_medical');

        $this->accountA = Account::create([
            'user_id'           => $this->delegate->id,
            'nom'               => 'Hôpital Général Félix Houphouët-Boigny',
            'account_type'      => 'hopital',
            'gps_lat'           => 5.3450,
            'gps_lng'           => -3.9820,
            'statut_validation' => 'valide',
        ]);

        $this->accountB = Account::create([
            'user_id'           => $this->delegate->id,
            'nom'               => 'Clinique Saint Viateur',
            'account_type'      => 'clinique',
            'gps_lat'           => 5.3500,
            'gps_lng'           => -3.9850,
            'statut_validation' => 'valide',
        ]);

        $this->product = Product::create([
            'nom'        => 'Skynity Cardia 50mg',
            'code'       => 'SKN-CARD-50',
            'is_active'  => true,
        ]);
    }

    /**
     * Scénario 1: Planification et exécution d'une visite DUO, de la planification à la clôture (Superviseur puis Délégué)
     */
    public function test_scenario_1_duo_from_planning_to_closure(): void
    {
        // Étape 1 à 3: Le Superviseur consulte le planning et planifie la visite DUO sans imposer le médecin
        Sanctum::actingAs($this->supervisor);

        $duoResponse = $this->postJson('/api/v1/activities/duo', [
            'user_id'       => $this->delegate->id,
            'date_activite' => '2026-12-01',
            'heure_debut'   => '09:00',
            'heure_fin'     => '10:00',
            'notes'         => 'Accompagnement terrain DUO',
        ]);

        $duoResponse->assertStatus(201)
            ->assertJsonPath('data.statut', 'en_attente');

        $activityId = $duoResponse->json('data.id');

        // Créer et valider un prescripteur pour le délégué
        $contact = Contact::create([
            'nom'               => 'Kouassi',
            'prenom'            => 'Jean-Marc',
            'user_id'           => $this->delegate->id,
            'statut_validation' => 'valide',
            'statut'            => 'actif',
        ]);

        // Étape 4 à 6: Le Délégué consulte sa notification, renseigne le médecin, l'établissement et accepte
        Sanctum::actingAs($this->delegate);

        $acceptResponse = $this->postJson("/api/v1/activities/{$activityId}/accept-duo", [
            'contact_id'    => $contact->id,
            'account_id'    => $this->accountA->id,
            'activity_type' => 'visite',
            'objective'     => 'Présentation Cardia 50mg',
            'products'      => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'customer_interest' => 'fort']
            ],
        ]);

        $acceptResponse->assertStatus(200)
            ->assertJsonPath('data.statut', 'validee');

        // Étape 8: Le Délégué démarre la visite (Check-in GPS -> en_cours)
        $startResponse = $this->putJson("/api/v1/activities/{$activityId}", [
            'statut'        => 'en_cours',
            'gps_debut_lat' => 5.3450,
            'gps_debut_lng' => -3.9820,
        ]);

        $startResponse->assertStatus(200)
            ->assertJsonPath('data.statut', 'en_cours');

        // Arrêt de la visite (Check-out GPS -> terminee)
        $stopResponse = $this->putJson("/api/v1/activities/{$activityId}", [
            'statut'        => 'terminee',
            'gps_fin_lat'   => 5.3451,
            'gps_fin_lng'   => -3.9821,
            'heure_fin'     => '10:00',
        ]);

        $stopResponse->assertStatus(200);

        // Étape 9 et 10: Rédaction du compte-rendu puis soumission (statut -> realisee / cloturee)
        $reportResponse = $this->putJson("/api/v1/activities/{$activityId}/report", [
            'report_content' => 'Visite DUO très fructueuse. Le médecin montre un fort intérêt pour Skynity Cardia.',
            'report_status'  => 'submitted',
        ]);

        $reportResponse->assertStatus(200);

        $this->assertDatabaseHas('activities', [
            'id'        => $activityId,
            'is_locked' => true,
        ]);
    }

    /**
     * Scénario 2: Planification et exécution d'une visite TRIO, de la création à la clôture (Sales Manager, Superviseur puis Délégué)
     */
    public function test_scenario_2_trio_from_creation_to_closure(): void
    {
        // Créer un contact validé
        $contact = Contact::create([
            'nom'               => 'Bamba',
            'prenom'            => 'Salimata',
            'user_id'           => $this->delegate->id,
            'statut_validation' => 'valide',
            'statut'            => 'actif',
        ]);

        // Étape 1 & 2: Le Sales Manager crée le TRIO et le transmet au Superviseur
        Sanctum::actingAs($this->salesManager);

        $trioResponse = $this->postJson('/api/v1/activities/trio', [
            'user_id'       => $this->delegate->id,
            'contact_id'    => $contact->id,
            'account_id'    => $this->accountB->id,
            'date_activite' => '2026-12-05',
            'heure_debut'   => '14:00',
            'heure_fin'     => '15:00',
            'activity_type' => 'visite',
            'notes'         => 'Revue stratégique hôpital avec la direction',
        ]);

        $trioResponse->assertStatus(201)
            ->assertJsonPath('data.statut', 'en_attente');

        $trioId = $trioResponse->json('data.id');

        // Étape 3: Le Superviseur valide la visite TRIO
        Sanctum::actingAs($this->supervisor);

        $valResponse = $this->postJson("/api/v1/activities/{$trioId}/validate-trio");
        $valResponse->assertStatus(200)
            ->assertJsonPath('data.statut', 'validee');

        // Étape 4 à 8: Le Délégué réalise la visite et soumet son rapport
        Sanctum::actingAs($this->delegate);

        $this->putJson("/api/v1/activities/{$trioId}", [
            'statut'        => 'en_cours',
            'gps_debut_lat' => 5.3500,
            'gps_debut_lng' => -3.9850,
        ])->assertStatus(200);

        $this->putJson("/api/v1/activities/{$trioId}/report", [
            'report_content' => 'Visite TRIO exécutée et rapport validé.',
            'report_status'  => 'submitted',
        ])->assertStatus(200);

        $this->assertDatabaseHas('activities', [
            'id'        => $trioId,
            'is_locked' => true,
        ]);
    }

    /**
     * Scénario 3: Création d'un nouveau prescripteur avec plusieurs établissements jusqu'à sa validation (Délégué puis Superviseur)
     */
    public function test_scenario_3_contact_creation_multi_account_to_validation(): void
    {
        // Étape 1 à 5: Le Délégué crée le prescripteur et le rattache à plusieurs établissements
        Sanctum::actingAs($this->delegate);

        $createResponse = $this->postJson('/api/v1/contacts', [
            'nom'          => 'N\'Dri',
            'prenom'       => 'Philippe',
            'specialite'   => 'Cardiologie',
            'service'      => 'Cardiologie Interventionnelle',
            'poste'        => 'Praticien Hospitalier',
            'telephone1'   => '+225 0701020304',
            'telephone2'   => '+225 0501020304',
            'fonction'     => 'chef_de_service',
            'accounts'     => [$this->accountA->id, $this->accountB->id],
            'primary_account_id' => $this->accountA->id,
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.statut_validation', 'en_attente');

        $contactId = $createResponse->json('data.id');

        // Vérification de la liaison multiple dans la table pivot account_contact
        $this->assertDatabaseHas('account_contact', [
            'contact_id' => $contactId,
            'account_id' => $this->accountA->id,
        ]);
        $this->assertDatabaseHas('account_contact', [
            'contact_id' => $contactId,
            'account_id' => $this->accountB->id,
        ]);

        // Étape 6: Le Superviseur valide le prescripteur
        Sanctum::actingAs($this->supervisor);

        $validateResponse = $this->postJson("/api/v1/contacts/{$contactId}/valider");
        $validateResponse->assertStatus(200)
            ->assertJsonPath('data.statut_validation', 'valide');

        // Étape 7: Le prescripteur est désormais validé et sélectionnable pour les visites
        $this->assertDatabaseHas('contacts', [
            'id'                => $contactId,
            'statut_validation' => 'valide',
        ]);
    }
}
