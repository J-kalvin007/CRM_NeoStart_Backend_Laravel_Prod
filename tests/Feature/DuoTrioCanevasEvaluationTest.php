<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Role;
use App\Models\CoachingSession;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Notification;

class DuoTrioCanevasEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected User $dv;
    protected User $supervisor;
    protected User $delegate;
    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'directeur_ventes', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'delegue_medical', 'guard_name' => 'web']);

        $this->dv = User::factory()->create(['name' => 'Directeur Ventes']);
        $this->dv->assignRole('directeur_ventes');

        $this->supervisor = User::factory()->create([
            'name' => 'Superviseur Test',
            'manager_id' => $this->dv->id,
        ]);
        $this->supervisor->assignRole('superviseur');

        $this->delegate = User::factory()->create([
            'name' => 'Delegue Medical Test',
            'manager_id' => $this->supervisor->id,
        ]);
        $this->delegate->assignRole('delegue_medical');

        $this->contact = Contact::create([
            'nom'               => 'Kouame',
            'prenom'            => 'Dr Jean',
            'user_id'           => $this->delegate->id,
            'statut_validation' => 'valide',
            'statut'            => 'actif',
        ]);
    }

    /**
     * Requirement 5: Ne pas permettre au délégué d'effectuer une visite non validée.
     */
    public function test_delegate_cannot_start_unvalidated_visit(): void
    {
        Sanctum::actingAs($this->delegate);

        $activity = Activity::create([
            'user_id' => $this->delegate->id,
            'contact_id' => $this->contact->id,
            'statut' => 'planifiee',
            'validation_status' => 'pending',
            'is_validated' => false,
            'date_activite' => now()->toDateString(),
            'heure_debut' => '08:30:00',
            'heure_fin' => '09:00:00',
            'activity_type' => 'VISITE_MEDICALE',
        ]);

        $response = $this->postJson("/api/v1/activities/{$activity->id}/start", [
            'latitude' => 5.345,
            'longitude' => -4.024,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'VISIT_NOT_VALIDATED');
    }

    /**
     * Requirement 5: Validated visit CAN be started.
     */
    public function test_delegate_can_start_validated_visit(): void
    {
        Sanctum::actingAs($this->delegate);

        $activity = Activity::create([
            'user_id' => $this->delegate->id,
            'contact_id' => $this->contact->id,
            'statut' => 'validee',
            'validation_status' => 'approved',
            'is_validated' => true,
            'date_activite' => now()->toDateString(),
            'heure_debut' => '09:00:00',
            'heure_fin' => '09:30:00',
            'activity_type' => 'VISITE_MEDICALE',
        ]);

        $response = $this->postJson("/api/v1/activities/{$activity->id}/start", [
            'latitude' => 5.345,
            'longitude' => -4.024,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('en_cours', $activity->fresh()->statut);
    }

    /**
     * Requirement 6: Permettre au manager de modifier les planning des délégués.
     */
    public function test_manager_can_update_delegate_planning(): void
    {
        Sanctum::actingAs($this->supervisor);

        $activity = Activity::create([
            'user_id' => $this->delegate->id,
            'contact_id' => $this->contact->id,
            'statut' => 'planifiee',
            'date_activite' => now()->addDays(2)->toDateString(),
            'heure_debut' => '09:00:00',
            'heure_fin' => '09:30:00',
            'activity_type' => 'VISITE_MEDICALE',
        ]);

        $newDate = now()->addDays(4)->toDateString();

        $response = $this->putJson("/api/v1/activities/{$activity->id}", [
            'date_activite' => $newDate,
            'heure_debut' => '11:00',
            'heure_fin' => '11:45',
            'notes' => 'Planning décalé par le superviseur',
        ]);

        $response->assertStatus(200);

        $fresh = $activity->fresh();
        $this->assertEquals($newDate, $fresh->date_activite->toDateString());
        $this->assertStringStartsWith('11:00', (string)$fresh->heure_debut);
    }

    /**
     * Requirement 7, 9, 10: Évaluation DUO par le superviseur avec note sur 20 et respect canevas.
     */
    public function test_supervisor_evaluates_duo_session_with_canevas_and_note_sur_20(): void
    {
        Sanctum::actingAs($this->supervisor);

        $canevas = [
            'preparation_ciblage' => 4,
            'introduction_decouverte' => 4,
            'argumentation_produit_clm' => 3,
            'traitement_objections' => 4,
            'conclusion_engagements' => 5,
        ]; // Total: 20 points -> 20/20 -> 100%

        $response = $this->postJson('/api/v1/coaching/evaluate', [
            'session_type' => 'DUO',
            'evaluated_id' => $this->delegate->id,
            'session_date' => now()->toDateString(),
            'note_sur_20' => 20,
            'respect_canevas_pct' => 100,
            'points_forts' => 'Excellente maîtrise du ciblage et conclusion percutante.',
            'axes_amelioration' => 'Poursuivre la fluidité sur le support CLM.',
            'canevas_evaluation' => $canevas,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.note_sur_20', 20)
            ->assertJsonPath('data.respect_canevas_pct', 100);

        // Check DUO Dashboard
        $dashResponse = $this->getJson('/api/v1/coaching/duo-dashboard');
        $dashResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Requirement 9: La note accordée au délégué est visible dans son compte.
     */
    public function test_delegate_can_view_their_evaluations_in_account(): void
    {
        // Create an evaluation session for delegate
        CoachingSession::create([
            'session_type' => 'DUO',
            'supervisor_id' => $this->supervisor->id,
            'evaluator_id' => $this->supervisor->id,
            'delegate_id' => $this->delegate->id,
            'evaluated_id' => $this->delegate->id,
            'date_duo' => now()->toDateString(),
            'status' => 'completed',
            'note_globale' => 4,
            'note_sur_20' => 16,
            'respect_canevas_pct' => 80,
            'points_forts' => 'Bon relationnel avec le médecin.',
            'axes_amelioration' => 'Mieux préparer les chiffres clés.',
        ]);

        Sanctum::actingAs($this->delegate);

        $response = $this->getJson('/api/v1/coaching/my-evaluations');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals(16, $data[0]['note_sur_20']);
        $this->assertEquals(80, $data[0]['respect_canevas_pct']);
        $this->assertEquals('Superviseur Test', $data[0]['evaluator_name']);
    }

    /**
     * Requirement 8 & 11: Évaluation TRIO des superviseurs par le DV et Dashboard TRIO.
     */
    public function test_dv_evaluates_supervisor_in_trio_and_consults_trio_dashboard(): void
    {
        Sanctum::actingAs($this->dv);

        $response = $this->postJson('/api/v1/coaching/evaluate', [
            'session_type' => 'TRIO',
            'evaluated_id' => $this->supervisor->id,
            'session_date' => now()->toDateString(),
            'note_sur_20' => 18,
            'respect_canevas_pct' => 90,
            'points_forts' => 'Très bon accompagnement terrain et posture managériale.',
            'axes_amelioration' => 'Renforcer le débriefing immédiat.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.session_type', 'TRIO')
            ->assertJsonPath('data.note_sur_20', 18);

        $dashResponse = $this->getJson('/api/v1/coaching/trio-dashboard');
        $dashResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Requirement 1 & 2: Export DUO and TRIO states in Excel and PDF.
     */
    public function test_export_duo_and_trio_in_excel_and_pdf(): void
    {
        Sanctum::actingAs($this->supervisor);

        $resDuoExcel = $this->get('/api/v1/export/duos');
        $resDuoExcel->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $resDuoPdf = $this->get('/api/v1/export/duos/pdf');
        $resDuoPdf->assertStatus(200);

        Sanctum::actingAs($this->dv);

        $resTrioExcel = $this->get('/api/v1/export/trios');
        $resTrioExcel->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $resTrioPdf = $this->get('/api/v1/export/trios/pdf');
        $resTrioPdf->assertStatus(200);
    }

    /**
     * Requirement 1 & 2: Filter/Sort by delegate and country and export/print.
     */
    public function test_export_activities_with_sorting_by_delegate_and_country(): void
    {
        Sanctum::actingAs($this->supervisor);

        // Test export CSV sorted by delegate
        $csvResponse = $this->get('/api/v1/export/activities?sort_by=delegue&order_by=asc');
        $csvResponse->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // Test export PDF sorted by country
        $pdfResponse = $this->get('/api/v1/export/activities/pdf?sort_by=country&order_by=asc');
        $pdfResponse->assertStatus(200);
    }

    public function test_duo_dashboard_returns_real_delegate_details_counts_and_zero_scores(): void
    {
        foreach (['DUO', 'duo'] as $type) {
            CoachingSession::create([
                'session_type' => $type,
                'supervisor_id' => $this->supervisor->id,
                'delegate_id' => $this->delegate->id,
                'evaluated_id' => $this->delegate->id,
                'date_duo' => now()->toDateString(),
                'status' => 'completed',
                'note_sur_20' => 0,
                'respect_canevas_pct' => 0,
            ]);
        }

        Sanctum::actingAs($this->supervisor);
        $this->getJson('/api/v1/coaching/duo-dashboard')
            ->assertOk()
            ->assertJsonPath('kpis.total_duos', 2)
            ->assertJsonPath('kpis.avg_note_sur_20', 0)
            ->assertJsonPath('kpis.avg_respect_canevas', 0)
            ->assertJsonPath('delegates.0.delegate_id', $this->delegate->id)
            ->assertJsonPath('delegates.0.delegate_name', $this->delegate->name)
            ->assertJsonPath('delegates.0.delegate_email', $this->delegate->email)
            ->assertJsonPath('delegates.0.duos_count', 2)
            ->assertJsonPath('delegates.0.avg_respect_canevas', 0)
            ->assertJsonCount(2, 'delegates.0.evaluations');

        Sanctum::actingAs($this->delegate);
        $this->getJson('/api/v1/coaching/my-evaluations')
            ->assertOk()
            ->assertJsonPath('kpis.total_evaluations', 2)
            ->assertJsonPath('kpis.avg_note_sur_20', 0)
            ->assertJsonPath('kpis.avg_compliance_pct', 0);
    }

    public function test_trio_dashboard_returns_supervisor_identity_and_both_session_type_spellings(): void
    {
        foreach (['TRIO', 'trio'] as $type) {
            CoachingSession::create([
                'session_type' => $type,
                'supervisor_id' => $this->dv->id,
                'evaluator_id' => $this->dv->id,
                'delegate_id' => $this->supervisor->id,
                'evaluated_id' => $this->supervisor->id,
                'date_duo' => now()->toDateString(),
                'status' => 'completed',
                'note_sur_20' => 18,
                'respect_canevas_pct' => 90,
            ]);
        }

        Sanctum::actingAs($this->dv);
        $this->getJson('/api/v1/coaching/trio-dashboard')
            ->assertOk()
            ->assertJsonPath('kpis.total_trios', 2)
            ->assertJsonPath('kpis.avg_respect_canevas', 90)
            ->assertJsonPath('supervisors.0.supervisor_name', $this->supervisor->name)
            ->assertJsonPath('supervisors.0.supervisor_email', $this->supervisor->email)
            ->assertJsonPath('supervisors.0.trios_count', 2)
            ->assertJsonPath('supervisors.0.avg_note_sur_20', 18)
            ->assertJsonPath('supervisors.0.avg_respect_canevas', 90);
    }

    public function test_no_evaluation_has_no_measured_grade_or_compliance(): void
    {
        Sanctum::actingAs($this->supervisor);
        $this->getJson('/api/v1/coaching/duo-dashboard')
            ->assertOk()
            ->assertJsonPath('delegates.0.delegate_name', $this->delegate->name)
            ->assertJsonPath('delegates.0.duos_count', 0)
            ->assertJsonPath('delegates.0.avg_note_sur_20', null)
            ->assertJsonPath('delegates.0.avg_respect_canevas', null)
            ->assertJsonPath('kpis.avg_respect_canevas', null);

        Sanctum::actingAs($this->delegate);
        $this->getJson('/api/v1/coaching/my-evaluations')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('kpis.total_evaluations', 0)
            ->assertJsonPath('kpis.avg_note_sur_20', null);
    }

    public function test_country_filters_use_the_real_relation_and_preserve_team_visibility(): void
    {
        $country = \App\Models\Country::create(['name' => 'Togo', 'iso_code' => 'TG']);
        $this->delegate->update(['country_id' => $country->id]);
        $this->supervisor->update(['country_id' => $country->id]);
        $outsider = User::factory()->create(['country_id' => $country->id]);
        $outsider->assignRole('delegue_medical');

        CoachingSession::create([
            'session_type' => 'DUO',
            'supervisor_id' => $this->supervisor->id,
            'delegate_id' => $this->delegate->id,
            'evaluated_id' => $this->delegate->id,
            'date_duo' => now()->toDateString(),
            'status' => 'completed',
            'note_sur_20' => 16,
        ]);

        Sanctum::actingAs($this->supervisor);
        foreach (['Togo', 'TG', $country->id] as $value) {
            $this->getJson('/api/v1/coaching/duo-dashboard?country=' . $value)
                ->assertOk()
                ->assertJsonCount(1, 'delegates')
                ->assertJsonPath('delegates.0.delegate_name', $this->delegate->name)
                ->assertJsonPath('delegates.0.delegate_country', 'Togo')
                ->assertJsonPath('kpis.total_duos', 1);
        }
        $this->get('/api/v1/export/duos?country=Togo')->assertOk()->assertSee('Togo');
        $this->get('/api/v1/export/duos/pdf?country=Togo')->assertOk()->assertSee('Togo');

        Sanctum::actingAs($this->dv);
        $this->getJson('/api/v1/coaching/trio-dashboard?country=Togo')
            ->assertOk()
            ->assertJsonCount(1, 'supervisors')
            ->assertJsonPath('supervisors.0.supervisor_name', $this->supervisor->name)
            ->assertJsonPath('supervisors.0.supervisor_country', 'Togo');
    }
}

