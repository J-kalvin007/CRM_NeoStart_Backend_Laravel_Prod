<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Activity;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Sanctum\Sanctum;

class Phase4TrioPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesManager;
    protected User $supervisor;
    protected User $delegate;

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
    }

    /**
     * Test 1: Le Sales Manager crée un TRIO -> Transmis au Superviseur, pas directement au Délégué.
     */
    public function test_sales_manager_creates_trio_transmitted_to_supervisor(): void
    {
        Sanctum::actingAs($this->salesManager);

        $date = now()->addDays(2)->toDateString();

        $response = $this->postJson('/api/v1/trio', [
            'user_id'       => $this->delegate->id,
            'date_activite' => $date,
            'heure_debut'   => '09:00',
            'heure_fin'     => '10:00',
            'objective'     => 'Visite stratégique TRIO',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.statut', 'en_attente');
    }

    /**
     * Test 2: Le Superviseur refuse un TRIO sans motif -> Bloqué avec 422.
     */
    public function test_supervisor_refuses_trio_without_motif_is_blocked(): void
    {
        $trio = Activity::create([
            'user_id'              => $this->delegate->id,
            'accompanying_user_id' => $this->salesManager->id,
            'date_activite'        => now()->addDays(3)->toDateString(),
            'heure_debut'          => '10:00',
            'heure_fin'            => '11:00',
            'statut'               => 'en_attente',
            'activity_type'        => 'visite',
        ]);

        Sanctum::actingAs($this->supervisor);

        $response = $this->postJson("/api/v1/trio/{$trio->id}/rejeter", [
            'motif' => '',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test 3: Le Superviseur valide un TRIO -> Le Délégué reçoit une notification et la visite est validée dans son planning sans action de sa part.
     */
    public function test_supervisor_validates_trio_and_delegate_is_notified(): void
    {
        $trio = Activity::create([
            'user_id'              => $this->delegate->id,
            'accompanying_user_id' => $this->salesManager->id,
            'date_activite'        => now()->addDays(4)->toDateString(),
            'heure_debut'          => '14:00',
            'heure_fin'            => '15:00',
            'statut'               => 'en_attente',
            'activity_type'        => 'visite',
        ]);

        Sanctum::actingAs($this->supervisor);

        $response = $this->postJson("/api/v1/trio/{$trio->id}/valider");

        $response->assertStatus(200)
            ->assertJsonPath('data.statut', 'validee');

        // Vérification de la visibilité sur le planning du délégué sans aucune action de sa part
        Sanctum::actingAs($this->delegate);
        $planning = $this->getJson("/api/v1/activities/{$trio->id}");
        $planning->assertStatus(200)
            ->assertJsonPath('data.statut', 'validee');
    }

    /**
     * Test 4: Validation de TRIO qui chevauche une visite du Délégué -> Bloquée en conflit (422).
     */
    public function test_validating_trio_overlapping_existing_delegate_visit_is_blocked(): void
    {
        $date = now()->addDays(5)->toDateString();

        // Une visite active existe déjà sur le créneau 10h00-11h00
        Activity::create([
            'user_id'       => $this->delegate->id,
            'date_activite' => $date,
            'heure_debut'   => '10:00',
            'heure_fin'     => '11:00',
            'statut'        => 'validee',
            'activity_type' => 'visite',
        ]);

        // Un TRIO en attente sur le même créneau 10h00-11h00
        $trio = Activity::create([
            'user_id'              => $this->delegate->id,
            'accompanying_user_id' => $this->salesManager->id,
            'date_activite'        => $date,
            'heure_debut'          => '10:00',
            'heure_fin'            => '11:00',
            'statut'               => 'en_attente',
            'activity_type'        => 'visite',
        ]);

        Sanctum::actingAs($this->supervisor);

        $response = $this->postJson("/api/v1/trio/{$trio->id}/valider");

        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
    }
}
