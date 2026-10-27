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
            ->assertJsonPath('data.statut', 'brouillon');

        $this->assertNotNull(
            Activity::where('user_id', $this->delegate->id)
                ->where('activity_type', 'conge')
                ->where('statut', 'brouillon')
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

    private function submitLeaveWeek(): array
    {
        \Illuminate\Support\Facades\Mail::fake();
        Sanctum::actingAs($this->delegate);
        $start = now()->startOfWeek()->addWeek();
        $id = $this->postJson('/api/v1/leaves', [
            'type' => 'conge', 'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(4)->toDateString(), 'reason' => 'Congés annuels',
        ])->assertCreated()->json('data.id');
        $plan = $this->postJson('/api/v1/tour-plans', ['nom' => 'Semaine de congés',
            'date_debut' => $start->toDateString(), 'date_fin' => $start->copy()->addDays(6)->toDateString(),
            'statut' => 'soumis',
        ])->assertCreated()->json('data.id');
        return [$id, $plan];
    }

    public function test_supervisor_validates_leave_request_with_its_week(): void
    {
        [$id, $plan] = $this->submitLeaveWeek();
        Sanctum::actingAs($this->supervisor);
        $this->postJson("/api/v1/leaves/{$id}/validate")->assertUnprocessable();
        $this->postJson("/api/v1/validations/tour_plan/{$plan}/approve")->assertOk();
        $this->assertDatabaseHas('activities', ['id' => $id, 'statut' => 'validee']);
    }

    public function test_supervisor_rejects_leave_week_with_mandatory_reason(): void
    {
        [$id, $plan] = $this->submitLeaveWeek();
        Sanctum::actingAs($this->supervisor);
        $this->postJson("/api/v1/validations/tour_plan/{$plan}/reject")->assertUnprocessable();
        $this->postJson("/api/v1/validations/tour_plan/{$plan}/reject", [
            'rejection_reason' => 'Période de forte activité terrain.',
        ])->assertOk();
        $this->assertDatabaseHas('activities', ['id' => $id, 'statut' => 'refusee',
            'rejection_reason' => 'Période de forte activité terrain.']);
    }

    public function test_leave_contract_is_identical_after_creation_listing_and_editing(): void
    {
        Sanctum::actingAs($this->delegate);
        $data = ['type' => 'permission', 'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(22)->toDateString(), 'heure_debut' => '09:30',
            'heure_fin' => '16:15', 'reason' => 'Rendez-vous personnel'];
        $created = $this->postJson('/api/v1/leaves', $data)->assertCreated()
            ->assertJsonPath('data.days', 3)->assertJsonPath('data.planning_kind', 'permission')->json('data');
        $listed = $this->getJson('/api/v1/leaves')->assertOk()->json('data.0');
        $this->assertSame($created, $listed);
        $data['end_date'] = now()->addDays(23)->toDateString();
        $data['reason'] = 'Motif modifié';
        $edited = $this->putJson('/api/v1/leaves/'.$created['id'], $data)->assertOk()
            ->assertJsonPath('data.days_count', 4)->assertJsonPath('data.reason', 'Motif modifié')->json('data');
        $this->assertSame($edited, $this->getJson('/api/v1/leaves')->assertOk()->json('data.0'));
        $this->assertDatabaseHas('activities', ['id' => $created['id'], 'date_fin' => $data['end_date'].' 00:00:00', 'notes' => $data['reason']]);
        $this->getJson('/api/v1/activities?visits_only=1')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/activities')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_leave_dates_and_hours_are_validated_with_legacy_and_canonical_fields(): void
    {
        Sanctum::actingAs($this->delegate);
        $future = now()->addDays(20)->toDateString();
        $this->postJson('/api/v1/leaves', ['type' => 'conge', 'date_activite' => now()->subDay()->toDateString()])->assertUnprocessable();
        $this->postJson('/api/v1/leaves', ['type' => 'conge', 'start_date' => $future, 'heure_debut' => '25:00'])->assertUnprocessable();
        $this->postJson('/api/v1/leaves', ['type' => 'conge', 'start_date' => $future, 'heure_debut' => '16:00', 'heure_fin' => '09:00'])->assertUnprocessable();
    }
}
