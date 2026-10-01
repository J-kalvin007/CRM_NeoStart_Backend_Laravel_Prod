<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Sanctum\Sanctum;

class Phase7VisitLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $delegate;
    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'delegue_medical', 'guard_name' => 'web']);

        $this->delegate = User::factory()->create();
        $this->delegate->assignRole('delegue_medical');

        $this->contact = Contact::create([
            'nom'               => 'Sow',
            'prenom'            => 'Awa',
            'user_id'           => $this->delegate->id,
            'statut_validation' => 'valide',
            'statut'            => 'actif',
        ]);
    }

    /**
     * Test 1: Démarrer une visite puis quitter sans rédiger le CR -> Visite non bloquée, rapport en attente
     */
    public function test_start_and_stop_visit_without_immediate_report(): void
    {
        Sanctum::actingAs($this->delegate);

        $activity = Activity::create([
            'user_id'       => $this->delegate->id,
            'contact_id'    => $this->contact->id,
            'date_activite' => now()->toDateString(),
            'heure_debut'   => '09:00',
            'heure_fin'     => '10:00',
            'statut'        => 'planifiee',
            'activity_type' => 'visite',
        ]);

        // Step 1: Check-in (Démarrer)
        $startResp = $this->putJson("/api/v1/activities/{$activity->id}", [
            'statut'        => 'en_cours',
            'gps_debut_lat' => 5.3450,
            'gps_debut_lng' => -3.9820,
        ]);
        $startResp->assertStatus(200)
            ->assertJsonPath('data.statut', 'en_cours');

        // Step 2: Check-out (Arrêter la visite sans rédiger le rapport)
        $stopResp = $this->putJson("/api/v1/activities/{$activity->id}", [
            'statut'      => 'realisee',
            'gps_fin_lat' => 5.3452,
            'gps_fin_lng' => -3.9821,
        ]);
        $stopResp->assertStatus(200);

        // La visite n'est pas verrouillée immédiatement si le rapport est en attente
        $this->assertDatabaseHas('activities', [
            'id'     => $activity->id,
            'statut' => 'realisee',
        ]);
    }

    /**
     * Test 2: Revenir plus tard pour rédiger le CR (Brouillon accessible et modifiable)
     */
    public function test_draft_report_is_accessible_and_editable_before_final_submission(): void
    {
        Sanctum::actingAs($this->delegate);

        $activity = Activity::create([
            'user_id'        => $this->delegate->id,
            'contact_id'     => $this->contact->id,
            'date_activite'  => now()->toDateString(),
            'heure_debut'    => '10:00',
            'heure_fin'      => '11:00',
            'gps_debut_lat'  => 5.3450,
            'gps_debut_lng'  => -3.9820,
            'statut'         => 'realisee',
            'activity_type'  => 'visite',
            'report_content' => 'Brouillon initial...',
            'is_locked'      => false,
        ]);

        // Mise à jour du compte-rendu brouillon
        $updateResp = $this->putJson("/api/v1/activities/{$activity->id}", [
            'notes' => 'Mise à jour du compte rendu brouillon...',
        ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.notes', 'Mise à jour du compte rendu brouillon...');
    }

    /**
     * Test 3: Tenter de démarrer une visite annulée -> Refusé (403)
     */
    public function test_attempt_to_start_cancelled_visit_is_forbidden(): void
    {
        Sanctum::actingAs($this->delegate);

        $activity = Activity::create([
            'user_id'       => $this->delegate->id,
            'contact_id'    => $this->contact->id,
            'date_activite' => now()->toDateString(),
            'heure_debut'   => '14:00',
            'statut'        => 'annulee',
            'activity_type' => 'visite',
        ]);

        $response = $this->putJson("/api/v1/activities/{$activity->id}", [
            'statut'        => 'en_cours',
            'gps_debut_lat' => 5.3450,
            'gps_debut_lng' => -3.9820,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 4: Finalisation d'une visite sans GPS de début -> Refusée avec 422
     */
    public function test_finalizing_visit_without_start_gps_is_blocked(): void
    {
        Sanctum::actingAs($this->delegate);

        $activity = Activity::create([
            'user_id'       => $this->delegate->id,
            'contact_id'    => $this->contact->id,
            'date_activite' => now()->toDateString(),
            'heure_debut'   => '15:00',
            'statut'        => 'planifiee',
            'activity_type' => 'visite',
        ]);

        $response = $this->putJson("/api/v1/activities/{$activity->id}", [
            'statut' => 'realisee',
        ]);

        $response->assertStatus(422);
    }
}
