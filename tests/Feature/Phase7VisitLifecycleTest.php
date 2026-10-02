<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Product;
use App\Models\SampleMovement;
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
        $startedAt = now()->toIso8601String();
        $startResp = $this->putJson("/api/v1/activities/{$activity->id}", [
            'statut'        => 'en_cours',
            'gps_debut_lat' => 5.3450,
            'gps_debut_lng' => -3.9820,
            'start_time'    => $startedAt,
        ]);
        $startResp->assertStatus(200)
            ->assertJsonPath('data.statut', 'en_cours');
        $this->assertNotEmpty($startResp->json('data.start_time'));
        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'gps_debut_lat' => 5.3450,
            'gps_debut_lng' => -3.9820,
            'start_time' => \Carbon\Carbon::parse($startedAt)->format('Y-m-d H:i:s'),
        ]);

        // Step 2: Check-out (Arrêter la visite sans rédiger le rapport)
        $endedAt = now()->toIso8601String();
        $stopResp = $this->putJson("/api/v1/activities/{$activity->id}", [
            'statut'      => 'realisee',
            'gps_fin_lat' => 5.3452,
            'gps_fin_lng' => -3.9821,
            'end_time'    => $endedAt,
        ]);
        $stopResp->assertStatus(200);

        // La visite n'est pas verrouillée immédiatement si le rapport est en attente
        $this->assertDatabaseHas('activities', [
            'id'     => $activity->id,
            'statut' => 'realisee',
            'end_time' => \Carbon\Carbon::parse($endedAt)->format('Y-m-d H:i:s'),
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

    public function test_starting_visit_without_gps_is_blocked(): void
    {
        Sanctum::actingAs($this->delegate);

        $activity = Activity::create([
            'user_id'       => $this->delegate->id,
            'contact_id'    => $this->contact->id,
            'date_activite' => now()->toDateString(),
            'heure_debut'   => '14:00',
            'statut'        => 'planifiee',
            'activity_type' => 'visite',
        ]);

        $response = $this->putJson("/api/v1/activities/{$activity->id}", [
            'statut' => 'en_cours',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Démarrage impossible : le Check-in GPS est obligatoire.');
        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'statut' => 'planifiee',
        ]);
    }

    public function test_only_assigned_delegate_can_start_or_finish_visit(): void
    {
        $activity = Activity::create([
            'user_id' => $this->delegate->id,
            'contact_id' => $this->contact->id,
            'date_activite' => now()->toDateString(),
            'heure_debut' => '14:00',
            'statut' => 'planifiee',
            'activity_type' => 'visite',
        ]);

        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        $supervisor = User::factory()->create();
        $supervisor->assignRole('superviseur');
        $this->delegate->update(['manager_id' => $supervisor->id]);
        Sanctum::actingAs($supervisor);

        $startResponse = $this->putJson("/api/v1/activities/{$activity->id}", [
            'statut' => 'en_cours',
            'gps_debut_lat' => 5.3450,
            'gps_debut_lng' => -3.9820,
        ]);
        $startResponse->assertStatus(403)
            ->assertJsonPath('message', 'Seul le délégué auquel cette visite est attribuée peut la démarrer ou la terminer.');

        $activity->update([
            'statut' => 'en_cours',
            'gps_debut_lat' => 5.3450,
            'gps_debut_lng' => -3.9820,
        ]);

        $endResponse = $this->putJson("/api/v1/activities/{$activity->id}", [
            'statut' => 'realisee',
        ]);
        $endResponse->assertStatus(403)
            ->assertJsonPath('message', 'Seul le délégué auquel cette visite est attribuée peut la démarrer ou la terminer.');
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

    public function test_finishing_visit_records_sample_distribution_and_reduces_delegate_stock(): void
    {
        $product = Product::create(['nom' => 'Produit échantillon', 'code' => 'SAMPLE-TEST', 'prix' => 100]);
        $activity = Activity::create([
            'user_id' => $this->delegate->id,
            'contact_id' => $this->contact->id,
            'date_activite' => now()->toDateString(),
            'heure_debut' => '09:00',
            'statut' => 'en_cours',
            'activity_type' => 'visite',
            'gps_debut_lat' => 5.3450,
            'gps_debut_lng' => -3.9820,
            'start_time' => now()->subMinutes(20),
        ]);
        SampleMovement::create([
            'user_id' => $this->delegate->id,
            'product_id' => $product->id,
            'movement_type' => 'reception',
            'quantity' => 10,
            'lot_number' => 'LOT-SAMPLE-01',
        ]);

        Sanctum::actingAs($this->delegate);
        $response = $this->putJson("/api/v1/activities/{$activity->id}", [
            'statut' => 'realisee',
            'gps_fin_lat' => 5.3452,
            'gps_fin_lng' => -3.9821,
            'end_time' => now()->toIso8601String(),
            'samples' => [[
                'product_id' => $product->id,
                'lot_number' => 'LOT-SAMPLE-01',
                'quantity' => 3,
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.statut', 'realisee')
            ->assertJsonPath('data.sample_movements.0.quantity', 3)
            ->assertJsonPath('data.sample_movements.0.lot_number', 'LOT-SAMPLE-01');
        $this->assertDatabaseHas('sample_movements', [
            'activity_id' => $activity->id,
            'contact_id' => $this->contact->id,
            'product_id' => $product->id,
            'movement_type' => 'distribution',
            'quantity' => 3,
            'lot_number' => 'LOT-SAMPLE-01',
            'stock_apres' => 7,
        ]);
        $this->getJson('/api/v1/my-stock')
            ->assertOk()
            ->assertJsonPath('0.quantity_available', 7);
        $this->getJson('/api/v1/activities')
            ->assertOk()
            ->assertJsonPath('data.0.sample_movements.0.quantity', 3);
    }

    public function test_insufficient_sample_stock_keeps_visit_in_progress_and_creates_no_distribution(): void
    {
        $product = Product::create(['nom' => 'Produit échantillon', 'code' => 'SAMPLE-LOW', 'prix' => 100]);
        $activity = Activity::create([
            'user_id' => $this->delegate->id,
            'contact_id' => $this->contact->id,
            'date_activite' => now()->toDateString(),
            'heure_debut' => '09:00',
            'statut' => 'en_cours',
            'activity_type' => 'visite',
            'gps_debut_lat' => 5.3450,
            'gps_debut_lng' => -3.9820,
        ]);
        SampleMovement::create([
            'user_id' => $this->delegate->id,
            'product_id' => $product->id,
            'movement_type' => 'reception',
            'quantity' => 2,
            'lot_number' => 'LOT-SAMPLE-02',
        ]);

        Sanctum::actingAs($this->delegate);
        $this->putJson("/api/v1/activities/{$activity->id}", [
            'statut' => 'realisee',
            'gps_fin_lat' => 5.3452,
            'gps_fin_lng' => -3.9821,
            'samples' => [[
                'product_id' => $product->id,
                'lot_number' => 'LOT-SAMPLE-02',
                'quantity' => 3,
            ]],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'statut' => 'en_cours']);
        $this->assertDatabaseMissing('sample_movements', [
            'activity_id' => $activity->id,
            'movement_type' => 'distribution',
        ]);
    }
}
