<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalendarPlanningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $kind = 'conge', array $extra = []): array
    {
        $start = now()->startOfWeek()->addWeek();
        return array_merge([
            'activity_type' => $kind, 'date_activite' => $start->toDateString(),
            'date_fin' => $start->copy()->addDays(4)->toDateString(),
            'heure_debut' => '08:00', 'heure_fin' => '18:00',
        ], $extra);
    }

    public function test_every_calendar_kind_is_submitted_and_approved_with_its_week(): void
    {
        Mail::fake();
        $manager = User::factory()->create();
        \App\Models\Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        $manager->assignRole('superviseur');
        foreach (['conge', 'permission', 'reunion', 'rcp'] as $kind) {
            $delegate = User::factory()->create(['manager_id' => $manager->id]);
            Sanctum::actingAs($delegate);
            $id = $this->postJson('/api/v1/activities', $this->event($kind))->assertCreated()
                ->assertJsonPath('data.statut', 'brouillon')->json('data.id');
            $plan = $this->postJson('/api/v1/tour-plans', [
                'nom' => 'Semaine', 'date_debut' => $this->event()['date_activite'],
                'date_fin' => $this->event()['date_fin'], 'statut' => 'soumis', 'activity_ids' => [$id],
            ])->assertCreated()->json('data.id');
            $unsubmitted = Activity::create($this->event('visite', ['user_id' => $delegate->id, 'statut' => 'brouillon']));
            $this->assertSame('en_attente', Activity::find($id)->statut);
            Sanctum::actingAs($manager);
            $planning = $this->getJson('/api/v1/supervisor/team-planning?date=' . $this->event()['date_activite'])
                ->assertOk();
            $scheduled = collect($planning->json('schedules'))->firstWhere('id', $id);
            $this->assertNotNull($scheduled, 'Submitted calendar must reach the supervisor');
            $this->assertSame($kind, $scheduled['planning_kind']);
            $this->assertFalse($scheduled['can_duo']);
            $this->assertStringNotContainsString('{', $scheduled['title']);
            $this->assertNotContains($unsubmitted->id, array_column($planning->json('schedules'), 'id'));
            $this->putJson("/api/v1/tour-plans/{$plan}", ['statut' => 'valide'])->assertOk();
            $this->assertSame('validee', Activity::withoutGlobalScopes()->find($id)->statut);
            $this->assertSame('brouillon', $unsubmitted->fresh()->statut);
        }
    }

    public function test_leave_screen_creates_one_draft_period_and_edits_both_dates(): void
    {
        Mail::fake();
        Sanctum::actingAs($delegate = User::factory()->create());
        $event = $this->event();
        $id = $this->postJson('/api/v1/leaves', [
            'type' => 'permission', 'start_date' => $event['date_activite'], 'end_date' => $event['date_fin'], 'reason' => 'Famille',
        ])->assertCreated()->assertJsonPath('data.status', 'brouillon')->json('data.id');
        $this->assertSame(1, Activity::where('user_id', $delegate->id)->count());
        $end = now()->startOfWeek()->addWeeks(2)->toDateString();
        $this->putJson("/api/v1/leaves/{$id}", ['date_fin' => $end])->assertOk();
        $this->getJson('/api/v1/leaves')->assertOk()->assertJsonPath('data.0.end_date', $end);
    }

    public function test_calendar_events_cannot_be_executed_or_submitted_as_individual_visits(): void
    {
        Mail::fake();
        Sanctum::actingAs($delegate = User::factory()->create());
        $id = $this->postJson('/api/v1/activities', $this->event())->assertCreated()->json('data.id');
        $this->putJson("/api/v1/activities/{$id}", ['statut' => 'en_attente'])->assertUnprocessable();
        $activity = Activity::find($id);
        $activity->update(['statut' => 'validee']);
        $this->postJson("/api/v1/activities/{$id}/start", ['latitude' => 6, 'longitude' => 1])->assertUnprocessable();
        $this->putJson("/api/v1/activities/{$id}", ['statut' => 'en_cours'])->assertUnprocessable();
    }

    public function test_passed_calendar_periods_and_rejected_visits_keep_their_decision(): void
    {
        $user = User::factory()->create();
        $type = ActivityType::where('code', 'conge')->firstOrFail();
        $type->update(['name' => 'Absence annuelle']);
        $leave = Activity::create($this->event('Absence annuelle', ['user_id' => $user->id, 'activity_type_id' => $type->id,
            'date_activite' => '2020-01-01', 'date_fin' => '2020-01-05', 'statut' => 'validee']));
        Activity::markMissedPastActivities();
        $this->assertSame('validee', $leave->fresh()->statut);
        $this->assertSame('Absence annuelle', $leave->fresh()->toArray()['activity_type_name']);
    }

    public function test_period_starting_before_the_week_can_be_submitted_and_rejected_as_a_whole(): void
    {
        Mail::fake();
        $manager = User::factory()->create();
        \App\Models\Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        $manager->assignRole('superviseur');
        Sanctum::actingAs($delegate = User::factory()->create(['manager_id' => $manager->id]));
        $event = $this->event();
        $id = $this->postJson('/api/v1/activities', $this->event('conge', [
            'date_activite' => now()->startOfWeek()->addWeek()->subDays(2)->toDateString(),
        ]))->assertCreated()->json('data.id');
        $plan = $this->postJson('/api/v1/tour-plans', ['nom' => 'Semaine', 'date_debut' => $event['date_activite'],
            'date_fin' => $event['date_fin'], 'statut' => 'soumis', 'activity_ids' => [$id],
        ])->assertCreated()->json('data.id');
        Sanctum::actingAs($manager);
        $planning = $this->getJson('/api/v1/supervisor/team-planning?date=' . $event['date_activite'])->assertOk();
        $this->assertContains($id, array_column($planning->json('schedules'), 'id'));
        $this->putJson("/api/v1/tour-plans/{$plan}", ['statut' => 'rejete', 'commentaire_validation' => 'Période indisponible'])->assertOk();
        $this->assertSame('refusee', Activity::withoutGlobalScopes()->find($id)->statut);
    }
    public function test_web_validation_list_and_decision_keep_the_whole_calendar_workflow(): void
    {
        Mail::fake();
        $manager = User::factory()->create();
        \App\Models\Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        $manager->assignRole('superviseur');
        Sanctum::actingAs($delegate = User::factory()->create(['manager_id' => $manager->id]));
        $event = $this->event();
        $id = $this->postJson('/api/v1/activities', $event)->assertCreated()->json('data.id');
        $plan = $this->postJson('/api/v1/tour-plans', ['nom' => 'Semaine congés',
            'date_debut' => $event['date_activite'], 'date_fin' => $event['date_fin'], 'statut' => 'soumis',
        ])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/activities/{$id}", ['notes' => 'Changement après soumission'])->assertUnprocessable();
        Sanctum::actingAs($manager);
        $items = $this->getJson('/api/v1/validations/pending')->assertOk()->json('data');
        $this->assertSame($plan, collect($items)->firstWhere('type', 'tour_plan')['id']);
        $this->postJson("/api/v1/validations/tour_plan/{$plan}/reject", ['reason' => 'À revoir'])->assertOk();
        $this->assertSame('refusee', Activity::withoutGlobalScopes()->find($id)->statut);
        Sanctum::actingAs($delegate);
        $this->deleteJson("/api/v1/tour-plans/{$plan}")->assertNoContent();
        $this->assertSame('brouillon', Activity::find($id)->statut);
        $this->assertNull(Activity::find($id)->tour_plan_id);
        $this->putJson("/api/v1/activities/{$id}", ['notes' => 'Période révisée'])->assertOk();
    }

    public function test_calendar_records_cannot_receive_visit_reports_or_individual_manager_decisions(): void
    {
        Mail::fake();
        $manager = User::factory()->create();
        \App\Models\Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        $manager->assignRole('superviseur');
        Sanctum::actingAs(User::factory()->create(['manager_id' => $manager->id]));
        $id = $this->postJson('/api/v1/activities', $this->event('reunion'))->assertCreated()->json('data.id');
        $this->putJson("/api/v1/activities/{$id}/report", ['report_status' => 'draft', 'report_content' => 'Compte-rendu'])->assertUnprocessable()->assertJsonPath('message', 'Cette activité ne nécessite pas de compte-rendu de visite.');
        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/validations/visit/{$id}/approve")->assertUnprocessable();
        $this->assertSame('brouillon', Activity::withoutGlobalScopes()->find($id)->statut);
    }

    public function test_permission_reserves_only_its_interval_and_visits_can_still_share_other_slots(): void
    {
        Mail::fake();
        Sanctum::actingAs($delegate = User::factory()->create());
        $date = now()->startOfWeek()->addWeek()->toDateString();
        $this->postJson('/api/v1/activities', $this->event('permission', [
            'date_activite' => $date, 'date_fin' => $date, 'heure_debut' => '14:00', 'heure_fin' => '16:00',
        ]))->assertCreated();
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/activities', ['activity_type' => 'visite',
                'date_activite' => $date, 'heure_debut' => '09:00', 'heure_fin' => '10:00',
            ])->assertCreated();
        }
        $this->postJson('/api/v1/activities', ['activity_type' => 'visite',
            'date_activite' => $date, 'heure_debut' => '15:00', 'heure_fin' => '15:30',
        ])->assertUnprocessable();
    }

}
