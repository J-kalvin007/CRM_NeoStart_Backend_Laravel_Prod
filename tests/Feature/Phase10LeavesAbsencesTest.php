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

class Phase10LeavesAbsencesTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;
    protected User $delegate;
    protected Contact $contact;

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

        $this->contact = Contact::create([
            'nom'               => 'Diallo',
            'prenom'            => 'Mamadou',
            'user_id'           => $this->delegate->id,
            'statut_validation' => 'valide',
            'statut'            => 'actif',
        ]);
    }

    /**
     * Test 1: Le Délégué soumet une demande de congé avec succès
     */
    public function test_delegate_submits_leave_request_successfully(): void
    {
        Sanctum::actingAs($this->delegate);

        $response = $this->postJson('/api/v1/leaves', [
            'type'       => 'conge',
            'start_date' => '2026-11-10',
            'end_date'   => '2026-11-12',
            'reason'     => 'Congés annuels',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.statut', 'en_attente');

        $this->assertNotNull(
            Activity::where('user_id', $this->delegate->id)
                ->where('activity_type', 'conge')
                ->where('statut', 'en_attente')
                ->whereDate('date_activite', '2026-11-10')
                ->first()
        );
    }

    /**
     * Test 2: Un congé planifié/validé bloque la planification d'une visite (422)
     */
    public function test_leave_request_blocks_overlapping_visit_planning(): void
    {
        // Créer un congé validé sur le jour 2026-11-15
        Activity::create([
            'user_id'       => $this->delegate->id,
            'activity_type' => 'conge',
            'date_activite' => '2026-11-15',
            'statut'        => 'validee',
            'notes'         => 'Repos médical',
        ]);

        Sanctum::actingAs($this->delegate);

        // Tenter de créer une visite sur ce même jour
        $response = $this->postJson('/api/v1/activities', [
            'contact_id'    => $this->contact->id,
            'activity_type' => 'visite',
            'date_activite' => '2026-11-15',
            'heure_debut'   => '10:00',
            'heure_fin'     => '11:00',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test 3: Une visite existante bloque la soumission d'un congé sur le même créneau (422)
     */
    public function test_visit_blocks_overlapping_leave_request(): void
    {
        // Créer une visite planifiée sur le jour 2026-11-20
        Activity::create([
            'user_id'       => $this->delegate->id,
            'contact_id'    => $this->contact->id,
            'activity_type' => 'visite',
            'date_activite' => '2026-11-20',
            'heure_debut'   => '14:00',
            'heure_fin'     => '15:00',
            'statut'        => 'planifiee',
        ]);

        Sanctum::actingAs($this->delegate);

        // Tenter de créer un congé sur ce même jour
        $response = $this->postJson('/api/v1/leaves', [
            'type'       => 'conge',
            'start_date' => '2026-11-20',
            'end_date'   => '2026-11-20',
            'reason'     => 'Absence exceptionnelle',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test 4: Le Superviseur valide la demande de congé
     */
    public function test_supervisor_validates_leave_request(): void
    {
        $leave = Activity::create([
            'user_id'       => $this->delegate->id,
            'activity_type' => 'conge',
            'date_activite' => '2026-11-25',
            'statut'        => 'en_attente',
            'notes'         => 'Formation externe',
        ]);

        Sanctum::actingAs($this->supervisor);

        $response = $this->postJson("/api/v1/leaves/{$leave->id}/validate");

        $response->assertStatus(200)
            ->assertJsonPath('data.statut', 'validee');

        $this->assertDatabaseHas('activities', [
            'id'     => $leave->id,
            'statut' => 'validee',
        ]);
    }

    /**
     * Test 5: Le rejet de congé sans motif échoue (422), et avec motif passe en 'rejetee'
     */
    public function test_supervisor_rejects_leave_request_requires_motif(): void
    {
        $leave = Activity::create([
            'user_id'       => $this->delegate->id,
            'activity_type' => 'conge',
            'date_activite' => '2026-11-30',
            'statut'        => 'en_attente',
            'notes'         => 'Vacances de fin d\'année',
        ]);

        Sanctum::actingAs($this->supervisor);

        // Rejet sans motif -> Doit retourner 422
        $failResponse = $this->postJson("/api/v1/leaves/{$leave->id}/reject", []);
        $failResponse->assertStatus(422);

        // Rejet avec motif obligatoire -> 200 OK
        $successResponse = $this->postJson("/api/v1/leaves/{$leave->id}/reject", [
            'motif' => 'Période de forte activité terrain non autorisée.'
        ]);

        $successResponse->assertStatus(200)
            ->assertJsonPath('data.statut', 'rejetee')
            ->assertJsonPath('data.rejection_reason', 'Période de forte activité terrain non autorisée.');

        $this->assertDatabaseHas('activities', [
            'id'               => $leave->id,
            'statut'           => 'rejetee',
            'rejection_reason' => 'Période de forte activité terrain non autorisée.',
        ]);
    }
}
