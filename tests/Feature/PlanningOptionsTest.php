<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanningOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_follow_database_labels_activation_and_server_reminder_rule(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $type = ActivityType::where('code', 'reunion')->firstOrFail();
        $type->update(['name' => 'Réunion de secteur']);
        ActivityType::where('code', 'permission')->update(['is_active' => false]);
        config(['planning.visit_reminder_minutes' => 7]);
        $response = $this->getJson('/api/v1/planning-options?date=2026-10-22')->assertOk()
            ->assertJsonPath('data.visit_reminder_minutes', 7)->assertJsonPath('data.outside_weekly_limit', 5);
        $types = collect($response->json('data.calendar_types'));
        $this->assertSame('Réunion de secteur', $types->firstWhere('code', 'reunion')['label']);
        $this->assertNull($types->firstWhere('code', 'permission'));
    }

    public function test_quota_is_fresh_and_scoped_to_the_authenticated_delegate_and_selected_week(): void
    {
        $delegate = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($delegate);
        $attrs = ['user_id' => $delegate->id, 'activity_type' => 'visite', 'date_activite' => '2026-10-22', 'heure_debut' => '09:00', 'statut' => 'validee', 'outside_weekly_plan' => true];
        Activity::create($attrs)->delete();
        Activity::create(array_merge($attrs, ['date_activite' => '2026-10-23']));
        Activity::create(array_merge($attrs, ['user_id' => $other->id]));
        $this->getJson('/api/v1/planning-options?date=2026-10-22')->assertOk()
            ->assertJsonPath('data.outside_weekly_used', 2)->assertJsonPath('data.outside_weekly_remaining', 3)
            ->assertJsonPath('data.week_start', '2026-10-19')->assertJsonPath('data.week_end', '2026-10-25');
        $this->getJson('/api/v1/planning-options?date=2026-10-26')->assertOk()->assertJsonPath('data.outside_weekly_remaining', 5);
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/planning-options?date=2026-10-22')->assertOk()->assertJsonPath('data.outside_weekly_remaining', 4);
    }

    public function test_renamed_calendar_types_preserve_date_validation_and_api_classification(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->create());
        $type = ActivityType::where('code', 'conge')->firstOrFail();
        $type->update(['name' => 'Absence annuelle']);
        $payload = ['activity_type_id' => $type->id, 'activity_type' => $type->name, 'date_activite' => '2026-11-02', 'heure_debut' => '08:00'];
        $this->postJson('/api/v1/activities', $payload)->assertUnprocessable()->assertJsonValidationErrors('date_fin');
        $this->postJson('/api/v1/activities', $payload + ['date_fin' => '2026-11-04', 'heure_fin' => '18:00'])
            ->assertCreated()->assertJsonPath('data.activity_type', 'Absence annuelle')->assertJsonPath('data.planning_kind', 'conge');
        $this->getJson('/api/v1/leaves')->assertOk()->assertJsonPath('data.0.type', 'Absence annuelle')
            ->assertJsonPath('data.0.end_date', '2026-11-04')->assertJsonPath('data.0.days', 3);
    }

    public function test_server_can_reduce_the_allowance_but_never_exceed_five(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['planning.outside_weekly_limit' => 2]);
        $this->getJson('/api/v1/planning-options')->assertOk()->assertJsonPath('data.outside_weekly_limit', 2);
        config(['planning.outside_weekly_limit' => 100]);
        $this->getJson('/api/v1/planning-options')->assertOk()->assertJsonPath('data.outside_weekly_limit', 5);
    }
    public function test_new_visits_cannot_use_an_inactive_reference_type(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $type = ActivityType::create(['name' => 'Ancien type', 'code' => 'old_visit', 'is_active' => false]);
        $this->postJson('/api/v1/activities', [
            'activity_type_id' => $type->id, 'activity_type' => $type->name,
            'date_activite' => '2026-11-02', 'heure_debut' => '09:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('activity_type');
    }

}
