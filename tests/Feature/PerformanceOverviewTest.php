<?php

namespace Tests\Feature;

use App\Models\{Activity, Country, Objectif, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceOverviewTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private Country $togo;
    private Country $benin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 27)->startOfDay());
        foreach (['superadmin', 'superviseur', 'delegue-medical'] as $role) Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $this->togo = Country::create(['name' => 'Togo', 'iso_code' => 'TG']);
        $this->benin = Country::create(['name' => 'Bénin', 'iso_code' => 'BJ']);
        $this->admin = $this->person('superadmin');
        Sanctum::actingAs($this->admin);
    }

    private function person(string $role = 'delegue-medical', array $fields = []): User
    {
        $user = User::factory()->create(array_merge(['country_id' => $this->togo->id], $fields));
        $user->assignRole($role);
        return $user;
    }

    private function objective(User $user, array $fields = []): Objectif
    {
        return Objectif::create(array_merge(['user_id' => $user->id, 'country_id' => $user->country_id,
            'date_debut' => '2026-10-01', 'date_fin' => '2026-10-31', 'valeur_cible' => 10,
            'valeur_realisee' => 5, 'statut' => 'actif'], $fields));
    }

    public function test_country_role_and_user_filters_narrow_data_without_requiring_an_admin_team(): void
    {
        $tg = $this->person();
        $bj = $this->person('superviseur', ['country_id' => $this->benin->id]);
        $this->objective($tg); $this->objective($bj);
        $this->getJson('/api/v1/analytics/performance')->assertOk()->assertJsonCount(3, 'data');
        $this->withHeader('X-Country-Id', $this->benin->id)->getJson('/api/v1/analytics/performance')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $bj->id)
            ->assertJsonPath('data.0.country_name', 'Bénin')->assertJsonCount(1, 'meta.users');
        $this->withHeader('X-Country-Id', 'group');
        $role = Role::where('name', 'delegue-medical')->first();
        $this->getJson('/api/v1/analytics/performance?role_id='.$role->id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $tg->id);
        $this->getJson('/api/v1/analytics/performance?user_id='.$bj->id)->assertOk()->assertJsonCount(1, 'data');
        $this->withHeader('X-Country-Id', 'unknown')->getJson('/api/v1/analytics/performance')
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonCount(0, 'meta.roles');
    }

    public function test_attainment_uses_ratios_excluding_cancelled_and_outside_period_and_preserves_overachievement(): void
    {
        $user = $this->person();
        $this->objective($user);
        $this->objective($user, ['valeur_cible' => 200, 'valeur_realisee' => 300]);
        $this->objective($user, ['valeur_cible' => 0, 'valeur_realisee' => 10]);
        $this->objective($user, ['statut' => 'annule', 'valeur_realisee' => 1000]);
        $this->objective($user, ['date_debut' => '2026-09-01', 'date_fin' => '2026-09-30']);
        $this->getJson('/api/v1/analytics/performance?user_id='.$user->id)->assertOk()
            ->assertJsonPath('data.0.objective_count', 3)->assertJsonPath('data.0.measured_objectives', 2)
            ->assertJsonPath('data.0.achieved_objectives', 1)->assertJsonPath('data.0.attainment_rate', 100);
        $this->getJson('/api/v1/analytics/performance?period=custom&start_date=2026-09-01&end_date=2026-09-30&user_id='.$user->id)
            ->assertOk()->assertJsonPath('data.0.objective_count', 1)->assertJsonPath('data.0.attainment_rate', 50);
        $this->getJson('/api/v1/analytics/performance?period=custom&start_date=2026-10-31&end_date=2026-10-01')->assertUnprocessable();
        $this->getJson('/api/v1/analytics/performance?period=custom')->assertUnprocessable();
    }

    public function test_supervisor_only_sees_recursive_team_and_user_filter_never_grants_access(): void
    {
        $manager = $this->person('superviseur');
        $nested = $this->person('superviseur', ['manager_id' => $manager->id]);
        $own = $this->person('delegue-medical', ['manager_id' => $nested->id]);
        $stranger = $this->person();
        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/analytics/performance')->assertOk()->assertJsonCount(3, 'data')->assertJsonMissing(['id' => $stranger->id]);
        $this->getJson('/api/v1/analytics/performance?user_id='.$stranger->id)->assertOk()->assertJsonCount(0, 'data');
        Sanctum::actingAs($own);
        $this->getJson('/api/v1/analytics/performance')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
    }

    public function test_visits_exclude_leave_and_other_dates_and_missing_objectives_have_no_fabricated_target(): void
    {
        $user = $this->person();
        foreach (['realisee', 'planifiee', 'manquee'] as $status) Activity::create([
            'user_id' => $user->id, 'date_activite' => '2026-10-27', 'activity_type' => 'visite', 'statut' => $status]);
        Activity::create(['user_id' => $user->id, 'date_activite' => '2026-10-27', 'activity_type' => 'conge', 'statut' => 'realisee']);
        Activity::create(['user_id' => $user->id, 'date_activite' => '2026-09-27', 'activity_type' => 'visite', 'statut' => 'realisee']);
        $this->getJson('/api/v1/analytics/performance?user_id='.$user->id)->assertOk()
            ->assertJsonPath('data.0.planned_visits', 3)->assertJsonPath('data.0.completed_visits', 1)
            ->assertJsonPath('data.0.missed_visits', 1)->assertJsonPath('data.0.attainment_rate', null);
    }

    public function test_objectives_expose_poste_and_country_and_filter_by_role(): void
    {
        $user = $this->person(); $obj = $this->objective($user);
        $role = Role::where('name', 'delegue-medical')->first();
        $this->getJson('/api/v1/objectifs?role_id='.$role->id)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $obj->id)->assertJsonPath('data.0.roles.0.id', $role->id)
            ->assertJsonPath('data.0.country_id', $this->togo->id);
        $this->withHeader('X-Country-Id', $this->benin->id)->getJson('/api/v1/objectifs?role_id='.$role->id)
            ->assertOk()->assertJsonCount(0, 'data');
    }
    public function test_objectives_list_respects_hierarchy_even_for_same_country_peers(): void
    {
        $manager = $this->person('superviseur');
        $own = $this->person('delegue-medical', ['manager_id' => $manager->id]);
        $stranger = $this->person();
        $mine = $this->objective($own); $this->objective($stranger);
        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/objectifs')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson('/api/v1/objectifs?user_id='.$stranger->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_planning_versus_execution_is_explicit_and_approval_never_counts_as_completion(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(12, 0));
        $user = $this->person();
        $states = [
            ['realisee', '2026-10-26', '09:00', '10:00'],
            ['validee', '2026-10-28', '09:00', '10:00'],
            ['planifiee', '2026-10-26', '09:00', '10:00'],
            ['en_cours', '2026-10-27', '11:00', '11:45'],
            ['manquee', '2026-10-25', '09:00', '10:00'],
            ['planifiee', '2026-10-27', null, null],
            ['en_attente', '2026-10-29', '09:00', null],
            ['annulee', '2026-10-26', '09:00', '10:00'],
            ['refusee', '2026-10-26', '09:00', '10:00'],
            ['brouillon', '2026-10-26', '09:00', '10:00'],
        ];
        foreach ($states as [$status, $date, $start, $end]) {
            Activity::create(['user_id' => $user->id, 'activity_type' => 'visite', 'date_activite' => $date,
                'statut' => $status, 'heure_debut' => $start, 'heure_fin' => $end,
                'start_time' => $status === 'realisee' ? '2026-10-26 09:15:00' : null,
                'end_time' => $status === 'realisee' ? '2026-10-26 10:02:00' : null,
                'report_content' => 'SECRET NON EXPOSE']);
        }
        $response = $this->getJson('/api/v1/analytics/performance?user_id='.$user->id)->assertOk();
        foreach (['planned_visits'=>7,'completed_visits'=>1,'remaining_visits'=>6,'execution_rate'=>14.3,
            'in_progress_visits'=>1,'missed_visits'=>1,'overdue_visits'=>1,'upcoming_visits'=>2,'pending_visits'=>1,'excluded_visits'=>3] as $key=>$value) {
            $response->assertJsonPath('data.0.'.$key, $value);
        }
        $planning = collect($response->json('planning'));
        $done = $planning->firstWhere('state', 'completed');
        $this->assertSame(15, $done['start_delay_minutes']);
        $this->assertNotNull($done['actual_start']);
        $approved = $planning->firstWhere('visit_status', 'validee');
        $this->assertSame('upcoming', $approved['state']);
        $this->assertNull($approved['actual_start']);
        $waiting = $planning->firstWhere('visit_status', 'en_attente');
        $this->assertNull($waiting['planned_end']);
        $response->assertDontSee('SECRET NON EXPOSE');
    }

}
