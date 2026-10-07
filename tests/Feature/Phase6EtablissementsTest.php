<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Account;
use App\Models\Role;
use App\Models\OrganizationalUnit;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Sanctum\Sanctum;

class Phase6EtablissementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;
    protected User $delegate;
    protected OrganizationalUnit $unit;

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

        $this->unit = OrganizationalUnit::create(['name' => 'Secteur Lomé', 'type' => 'secteur', 'is_active' => true]);
        \App\Models\UserScope::create([
            'user_id' => $this->delegate->id,
            'organizational_unit_id' => $this->unit->id,
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Création d'établissement sans coordonnées GPS -> Bloquée avec 422
     */
    public function test_creation_without_gps_coordinates_is_blocked(): void
    {
        Sanctum::actingAs($this->delegate);

        $response = $this->postJson('/api/v1/accounts', [
            'nom'                    => 'Pharmacie Sans GPS',
            'organizational_unit_id' => $this->unit->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['gps_lat', 'gps_lng']]);
    }

    /**
     * Test 2: Création d'établissement avec GPS par un délégué -> Statut en_attente
     */
    public function test_delegate_creates_establishment_with_gps_pending_validation(): void
    {
        Sanctum::actingAs($this->delegate);

        $response = $this->postJson('/api/v1/accounts', [
            'nom'                    => 'PHARMACIE ST ANTOINE',
            'gps_lat'                => 5.3450,
            'gps_lng'                => -3.9820,
            'organizational_unit_id' => $this->unit->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.statut_validation', 'en_attente');
    }

    /**
     * Test 3: Validation par le Superviseur -> Statut valide
     */
    public function test_supervisor_validates_establishment(): void
    {
        $account = Account::create([
            'nom'               => 'CLINIQUE DE LA PAIX',
            'user_id'           => $this->delegate->id,
            'gps_lat'           => 5.3450,
            'gps_lng'           => -3.9820,
            'statut_validation' => 'en_attente',
            'statut'            => 'en_attente',
        ]);

        Sanctum::actingAs($this->supervisor);

        $response = $this->postJson("/api/v1/accounts/{$account->id}/valider");

        $response->assertStatus(200)
            ->assertJsonPath('data.statut_validation', 'valide');
    }

    /**
     * Test 4: Rejet par le Superviseur sans motif -> Bloqué avec 422
     */
    public function test_supervisor_rejects_establishment_without_motif_is_blocked(): void
    {
        $account = Account::create([
            'nom'               => 'HOPITAL DU CENTRE',
            'user_id'           => $this->delegate->id,
            'gps_lat'           => 5.3450,
            'gps_lng'           => -3.9820,
            'statut_validation' => 'en_attente',
        ]);

        Sanctum::actingAs($this->supervisor);

        $response = $this->postJson("/api/v1/accounts/{$account->id}/rejeter", [
            'motif' => '',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test 5: Planification d'une visite avec un établissement non validé -> Bloquée avec 422
     */
    public function test_planning_visit_with_unvalidated_establishment_is_blocked(): void
    {
        $unvalidatedAccount = Account::create([
            'nom'               => 'CLINIQUE NON VALIDEE',
            'user_id'           => $this->delegate->id,
            'gps_lat'           => 5.3450,
            'gps_lng'           => -3.9820,
            'statut_validation' => 'en_attente',
            'statut'            => 'en_attente',
        ]);

        Sanctum::actingAs($this->delegate);

        $response = $this->postJson('/api/v1/activities', [
            'account_id'    => $unvalidatedAccount->id,
            'date_activite' => now()->addDays(2)->toDateString(),
            'heure_debut'   => '09:00',
            'heure_fin'     => '10:00',
            'activity_type' => 'visite',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['account_id']]);
    }

    /**
     * Test 6: Planification d'une visite avec un établissement validé -> Réussie (201)
     */
    public function test_planning_visit_with_validated_establishment_succeeds(): void
    {
        $validatedAccount = Account::create([
            'nom'               => 'PHARMACIE VALIDEE',
            'user_id'           => $this->delegate->id,
            'gps_lat'           => 5.3450,
            'gps_lng'           => -3.9820,
            'statut_validation' => 'valide',
            'statut'            => 'actif',
        ]);

        Sanctum::actingAs($this->delegate);

        $response = $this->postJson('/api/v1/activities', [
            'account_id'    => $validatedAccount->id,
            'date_activite' => now()->addDays(3)->toDateString(),
            'heure_debut'   => '10:00',
            'heure_fin'     => '11:00',
            'activity_type' => 'visite',
        ]);

        $response->assertStatus(201);
    }
}
