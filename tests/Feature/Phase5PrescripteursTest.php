<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Contact;
use App\Models\Account;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Sanctum\Sanctum;

class Phase5PrescripteursTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;
    protected User $delegate;
    protected Account $account1;
    protected Account $account2;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'delegue_medical', 'guard_name' => 'web']);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('superviseur');

        $this->delegate = User::factory()->create(['manager_id' => $this->supervisor->id]);
        $this->delegate->assignRole('delegue_medical');

        $this->account1 = Account::create(['nom' => 'Clinique A', 'user_id' => $this->delegate->id, 'statut_validation' => 'valide', 'statut' => 'actif']);
        $this->account2 = Account::create(['nom' => 'Hôpital B', 'user_id' => $this->delegate->id, 'statut_validation' => 'valide', 'statut' => 'actif']);
    }

    /**
     * Test 1: Rattachement multi-établissements d'un prescripteur
     */
    public function test_prescripteur_can_be_attached_to_multiple_establishments(): void
    {
        Sanctum::actingAs($this->delegate);

        $response = $this->postJson('/api/v1/contacts', [
            'nom'         => 'N\'Guessan',
            'prenom'      => 'Kouassi',
            'titre'       => 'Dr.',
            'account_ids' => [$this->account1->id, $this->account2->id],
        ]);

        $response->assertStatus(201);
        $contactId = $response->json('data.id');

        $contact = Contact::withoutGlobalScopes()->find($contactId);
        $this->assertCount(2, $contact->accounts);
    }

    /**
     * Test 2: Création par un délégué -> Statut en_attente
     */
    public function test_delegate_creates_prescripteur_pending_validation(): void
    {
        Sanctum::actingAs($this->delegate);

        $response = $this->postJson('/api/v1/contacts', [
            'nom'    => 'Kouamé',
            'prenom' => 'Jean',
            'titre'  => 'Dr.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.statut_validation', 'en_attente');
    }

    /**
     * Test 3: Validation par le Superviseur -> Statut valide
     */
    public function test_supervisor_validates_prescripteur(): void
    {
        $contact = Contact::create([
            'nom'               => 'Diop',
            'prenom'            => 'Aminata',
            'user_id'           => $this->delegate->id,
            'statut_validation' => 'en_attente',
            'statut'            => 'en_attente',
        ]);

        Sanctum::actingAs($this->supervisor);

        $response = $this->postJson("/api/v1/contacts/{$contact->id}/valider");

        $response->assertStatus(200)
            ->assertJsonPath('data.statut_validation', 'valide');
    }

    /**
     * Test 4: Rejet par le Superviseur sans motif -> Bloqué avec 422
     */
    public function test_supervisor_rejects_prescripteur_without_motif_is_blocked(): void
    {
        $contact = Contact::create([
            'nom'               => 'Sow',
            'prenom'            => 'Mamadou',
            'user_id'           => $this->delegate->id,
            'statut_validation' => 'en_attente',
        ]);

        Sanctum::actingAs($this->supervisor);

        $response = $this->postJson("/api/v1/contacts/{$contact->id}/rejeter", [
            'motif' => '',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test 5: Tentative de planification d'une visite avec un prescripteur non validé -> Bloqué avec 422
     */
    public function test_planning_visit_with_non_validated_prescripteur_is_blocked(): void
    {
        $unvalidatedContact = Contact::create([
            'nom'               => 'Diallo',
            'prenom'            => 'Oumar',
            'user_id'           => $this->delegate->id,
            'statut_validation' => 'en_attente',
            'statut'            => 'en_attente',
        ]);

        Sanctum::actingAs($this->delegate);

        $response = $this->postJson('/api/v1/activities', [
            'contact_id'    => $unvalidatedContact->id,
            'date_activite' => now()->addDays(2)->toDateString(),
            'heure_debut'   => '09:00',
            'heure_fin'     => '10:00',
            'activity_type' => 'visite',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['contact_id']]);
    }

    /**
     * Test 6: Planification d'une visite avec un prescripteur validé -> Réussie (201)
     */
    public function test_planning_visit_with_validated_prescripteur_succeeds(): void
    {
        $validatedContact = Contact::create([
            'nom'               => 'Fall',
            'prenom'            => 'Fatou',
            'user_id'           => $this->delegate->id,
            'statut_validation' => 'valide',
            'statut'            => 'actif',
        ]);

        Sanctum::actingAs($this->delegate);

        $response = $this->postJson('/api/v1/activities', [
            'contact_id'    => $validatedContact->id,
            'date_activite' => now()->addDays(3)->toDateString(),
            'heure_debut'   => '10:00',
            'heure_fin'     => '11:00',
            'activity_type' => 'visite',
        ]);

        $response->assertStatus(201);
    }

    /**
     * Test 7: Création d'un prescripteur avec nom d'établissement ou account_ids -> résolution et affichage corrects
     */
    public function test_prescripteur_creation_with_establishment_name_resolves_and_displays_establishment(): void
    {
        Sanctum::actingAs($this->delegate);

        // 1. Création avec le nom de l'établissement
        $response = $this->postJson('/api/v1/contacts', [
            'nom'           => 'TestDoc',
            'prenom'        => 'Michel',
            'titre'         => 'Dr.',
            'etablissement' => 'Clinique A',
        ]);

        $response->assertStatus(201);
        $this->assertEquals($this->account1->id, $response->json('data.account_id'));
        $this->assertEquals('Clinique A', $response->json('data.etablissement'));
        $this->assertEquals('Clinique A', $response->json('data.account.nom'));

        // 2. Vérification lors de la récupération de la liste (par le superviseur)
        Sanctum::actingAs($this->supervisor);
        $listResponse = $this->getJson('/api/v1/contacts?search=TestDoc');
        $listResponse->assertStatus(200);
        $this->assertEquals('Clinique A', $listResponse->json('data.0.etablissement'));
        $this->assertEquals('Clinique A', $listResponse->json('data.0.account.nom'));

        // 3. Création avec account_ids (tableau d'IDs)
        $response2 = $this->postJson('/api/v1/contacts', [
            'nom'         => 'TestDoc2',
            'prenom'      => 'Sophie',
            'titre'       => 'Dr.',
            'account_ids' => [$this->account2->id],
        ]);

        $response2->assertStatus(201);
        $this->assertEquals($this->account2->id, $response2->json('data.account_id'));
        $this->assertEquals('Hôpital B', $response2->json('data.etablissement'));
        $this->assertEquals('Hôpital B', $response2->json('data.account.nom'));
    }
}
