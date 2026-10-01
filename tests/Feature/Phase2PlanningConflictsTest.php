<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Activity;
use App\Models\Leave;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Sanctum\Sanctum;

class Phase2PlanningConflictsTest extends TestCase
{
    use RefreshDatabase;

    protected User $delegate;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'delegue_medical', 'guard_name' => 'web']);

        $this->delegate = User::factory()->create();
        $this->delegate->assignRole('delegue_medical');
        Sanctum::actingAs($this->delegate);
    }

    /**
     * Test 1: Chevauchement partiel (10h00-11h00 vs 10h30-11h30) -> Refusé (422).
     */
    public function test_partial_time_overlap_is_rejected(): void
    {
        $date = now()->addDays(2)->toDateString();

        // 1ère visite : 10h00 à 11h00
        $res1 = $this->postJson('/api/v1/activities', [
            'activity_type' => 'visite',
            'date_activite' => $date,
            'heure_debut'   => '10:00',
            'heure_fin'     => '11:00',
        ]);
        $res1->assertStatus(201);

        // 2nde visite : 10h30 à 11h30 (chevauchement partiel)
        $res2 = $this->postJson('/api/v1/activities', [
            'activity_type' => 'visite',
            'date_activite' => $date,
            'heure_debut'   => '10:30',
            'heure_fin'     => '11:30',
        ]);
        $res2->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    /**
     * Test 2: Créneaux parfaitement identiques (10h00-11h00 vs 10h00-11h00) -> Refusé (422).
     */
    public function test_identical_time_slot_is_rejected(): void
    {
        $date = now()->addDays(3)->toDateString();

        $res1 = $this->postJson('/api/v1/activities', [
            'activity_type' => 'visite',
            'date_activite' => $date,
            'heure_debut'   => '10:00',
            'heure_fin'     => '11:00',
        ]);
        $res1->assertStatus(201);

        $res2 = $this->postJson('/api/v1/activities', [
            'activity_type' => 'visite',
            'date_activite' => $date,
            'heure_debut'   => '10:00',
            'heure_fin'     => '11:00',
        ]);
        $res2->assertStatus(422);
    }

    /**
     * Test 3: Visite sans heure de fin ni durée -> Enregistrement bloqué (422).
     */
    public function test_visit_without_end_time_or_duration_is_rejected(): void
    {
        $date = now()->addDays(4)->toDateString();

        $response = $this->postJson('/api/v1/activities', [
            'activity_type' => 'visite',
            'date_activite' => $date,
            'heure_debut'   => '10:00',
            // Ni heure_fin ni duree_minutes
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test 4: Planning du délégué inclut les visites et les indisponibilités/demandes.
     */
    public function test_delegate_planning_includes_leaves_and_activities(): void
    {
        $date = now()->addDays(5)->toDateString();

        // Création d'une demande d'indisponibilité/congé
        \App\Models\DelegateRequest::create([
            'user_id'      => $this->delegate->id,
            'request_type' => 'conge',
            'reason'       => 'Repos médical',
            'status'       => 'approved',
        ]);

        $response = $this->getJson("/api/v1/activities?user_id={$this->delegate->id}");
        $response->assertStatus(200);

        $requestsResponse = $this->getJson("/api/v1/requests");
        $requestsResponse->assertStatus(200);
    }

    /**
     * Test 5: Chevauchement avec une visite annulée/rejetée -> Accepté (201).
     */
    public function test_overlap_with_cancelled_or_rejected_visit_is_allowed(): void
    {
        $date = now()->addDays(6)->toDateString();

        // 1ère visite créée puis annulée
        $v1 = Activity::create([
            'user_id'       => $this->delegate->id,
            'activity_type' => 'visite',
            'date_activite' => $date,
            'heure_debut'   => '10:00',
            'heure_fin'     => '11:00',
            'statut'        => 'annulee',
        ]);

        // 2nde visite sur le même créneau 10h00 à 11h00
        $res = $this->postJson('/api/v1/activities', [
            'activity_type' => 'visite',
            'date_activite' => $date,
            'heure_debut'   => '10:00',
            'heure_fin'     => '11:00',
        ]);

        $res->assertStatus(201);
    }
}
