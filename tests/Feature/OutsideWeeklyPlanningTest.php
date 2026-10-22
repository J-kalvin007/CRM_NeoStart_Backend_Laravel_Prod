<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OutsideWeeklyPlanningTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return array_merge([
            'activity_type' => 'visite',
            'date_activite' => now()->startOfWeek()->addWeek()->toDateString(),
            'heure_debut' => '09:00',
            'outside_weekly_plan' => true,
        ], $extra);
    }

    public function test_only_five_visits_are_exempt_per_delegate_and_calendar_week(): void
    {
        Mail::fake();
        $delegate = User::factory()->create();
        Sanctum::actingAs($delegate);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/activities', $this->payload())->assertCreated()
                ->assertJsonPath('data.statut', 'validee')->assertJsonPath('data.outside_weekly_plan', true);
        }
        $this->postJson('/api/v1/activities', $this->payload())->assertUnprocessable()
            ->assertJsonValidationErrors('outside_weekly_plan');
        $this->postJson('/api/v1/activities', $this->payload(['date_activite' => now()->startOfWeek()->addWeeks(2)->toDateString()]))->assertCreated();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/activities', $this->payload())->assertCreated();
    }

    public function test_exempt_visit_can_start_without_supervisor_validation(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/activities', $this->payload(['date_activite' => today()->toDateString()]))
            ->assertCreated()->json('data.id');
        $this->postJson("/api/v1/activities/{$id}/start", ['latitude' => 6.13, 'longitude' => 1.21])
            ->assertOk()->assertJsonPath('data.statut', 'en_cours');
    }

    public function test_deleting_an_exempt_visit_does_not_reset_the_allowance(): void
    {
        $delegate = User::factory()->create();
        Sanctum::actingAs($delegate);
        for ($i = 0; $i < 5; $i++) {
            Activity::create($this->payload(['user_id' => $delegate->id, 'statut' => 'validee']))->delete();
        }
        $this->postJson('/api/v1/activities', $this->payload())->assertUnprocessable();
    }

    public function test_ordinary_visits_still_need_weekly_submission(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/activities', $this->payload(['outside_weekly_plan' => false]))
            ->assertCreated()->assertJsonPath('data.statut', 'brouillon');
    }

    public function test_end_date_is_saved_and_cannot_precede_start_date(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->create());
        $start = now()->startOfWeek()->addWeek();
        $this->postJson('/api/v1/activities', $this->payload([
            'outside_weekly_plan' => false, 'activity_type' => 'reunion', 'heure_fin' => '18:00',
            'date_fin' => $start->copy()->addDays(2)->toDateString(),
        ]))->assertCreated()->assertJsonPath('data.date_fin', $start->copy()->addDays(2)->toDateString());
        $this->postJson('/api/v1/activities', $this->payload(['date_fin' => $start->copy()->subDay()->toDateString()]))
            ->assertUnprocessable()->assertJsonValidationErrors('date_fin');
    }
    public function test_exempt_visit_cannot_move_into_an_already_full_week(): void
    {
        Mail::fake();
        $delegate = User::factory()->create();
        Sanctum::actingAs($delegate);
        for ($i = 0; $i < 5; $i++) {
            Activity::create($this->payload(['user_id' => $delegate->id, 'statut' => 'validee']));
        }
        $visit = Activity::create($this->payload([
            'user_id' => $delegate->id, 'statut' => 'validee',
            'date_activite' => now()->startOfWeek()->addWeeks(2)->toDateString(),
        ]));
        $this->postJson("/api/v1/activities/{$visit->id}/reschedule", [
            'date_activite' => $this->payload()['date_activite'], 'motif' => 'Changement de tournée',
        ])->assertUnprocessable()->assertJsonValidationErrors('outside_weekly_plan');
        $this->putJson("/api/v1/activities/{$visit->id}", [
            'date_activite' => $this->payload()['date_activite'],
        ])->assertUnprocessable()->assertJsonValidationErrors('outside_weekly_plan');
        $this->putJson("/api/v1/activities/{$visit->id}", ['user_id' => null])->assertUnprocessable();
    }

    public function test_rescheduling_does_not_bypass_validation_for_ordinary_drafts(): void
    {
        Mail::fake();
        $delegate = User::factory()->create();
        Sanctum::actingAs($delegate);
        $visit = Activity::create($this->payload(['user_id' => $delegate->id, 'statut' => 'brouillon', 'outside_weekly_plan' => false]));
        $this->postJson("/api/v1/activities/{$visit->id}/reschedule", [
            'date_activite' => $this->payload()['date_activite'], 'motif' => 'Déplacement du créneau',
        ])->assertOk()->assertJsonPath('data.statut', 'brouillon');
    }

    public function test_calendar_events_need_an_end_and_can_cover_a_full_week_without_establishment(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->create());
        $start = now()->startOfWeek()->addWeek();
        foreach (['rcp', 'reunion', 'conge', 'permission'] as $type) {
            $this->postJson('/api/v1/activities', $this->payload([
                'outside_weekly_plan' => false, 'activity_type' => $type,
            ]))->assertUnprocessable()->assertJsonValidationErrors('date_fin');
        }
        $id = $this->postJson('/api/v1/activities', $this->payload([
            'outside_weekly_plan' => false, 'activity_type' => 'rcp',
            'date_fin' => $start->copy()->addDays(4)->toDateString(), 'heure_fin' => '18:00',
        ]))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/tour-plans', [
            'nom' => 'Semaine RCP', 'date_debut' => $start->toDateString(),
            'date_fin' => $start->copy()->addDays(6)->toDateString(), 'statut' => 'soumis', 'activity_ids' => [$id],
        ])->assertCreated();
    }

}
