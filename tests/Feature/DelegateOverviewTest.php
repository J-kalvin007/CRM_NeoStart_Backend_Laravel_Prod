<?php

namespace Tests\Feature;

use App\Models\{Account, Activity, Contact, Country, Objectif, Role, TourPlan, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DelegateOverviewTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private Country $togo;
    private Country $benin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(12, 0));
        foreach (['superadmin','superviseur','delegue-medical','delegue_medical'] as $role) Role::firstOrCreate(['name'=>$role,'guard_name'=>'web']);
        $this->togo = Country::create(['name'=>'Togo','iso_code'=>'TG']);
        $this->benin = Country::create(['name'=>'Bénin','iso_code'=>'BJ']);
        $this->admin = User::factory()->create(); $this->admin->assignRole('superadmin');
        Sanctum::actingAs($this->admin);
    }

    private function delegate(array $fields = []): User
    {
        $user = User::factory()->create(array_merge(['country_id'=>$this->togo->id],$fields));
        $user->assignRole('delegue-medical'); return $user;
    }

    private function visit(User $person, array $fields = []): Activity
    {
        return Activity::create(array_merge(['user_id'=>$person->id,'activity_type'=>'visite','date_activite'=>'2026-10-27',
            'heure_debut'=>'09:00','heure_fin'=>'10:00','statut'=>'realisee'],$fields));
    }

    public function test_directory_and_detail_obey_country_and_hierarchy_and_hide_credentials(): void
    {
        $tg = $this->delegate(['name'=>'Afi Mensah']);
        $bj = $this->delegate(['country_id'=>$this->benin->id]);
        $this->getJson('/api/v1/delegates')->assertOk()->assertJsonCount(2,'data')->assertJsonMissingPath('data.0.password');
        $this->withHeader('X-Country-Id',$this->benin->id)->getJson('/api/v1/delegates')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$bj->id);
        $this->getJson('/api/v1/delegates/'.$tg->id.'/overview')->assertNotFound();
        $this->withHeader('X-Country-Id','group');
        $manager = User::factory()->create(); $manager->assignRole('superviseur');
        $own = $this->delegate(['manager_id'=>$manager->id]);
        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/delegates')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$own->id);
        $this->getJson('/api/v1/delegates/'.$tg->id.'/overview')->assertNotFound();
        Sanctum::actingAs($own);
        $this->getJson('/api/v1/delegates/'.$own->id.'/overview')->assertOk()->assertJsonPath('profile.id',$own->id)->assertJsonMissingPath('profile.password');
        $this->getJson('/api/v1/delegates/'.$tg->id.'/overview')->assertNotFound();
    }

    public function test_profile_contains_real_reports_planning_objectives_and_positions_without_draft_content(): void
    {
        $user = $this->delegate(['last_lat'=>6.1,'last_lng'=>1.2,'last_location_at'=>now()]);
        $this->visit($user,['report_status'=>'draft','report_content'=>'SECRET MOBILE DRAFT']);
        $published = $this->visit($user,['report_status'=>'submitted','report_content'=>str_repeat('Texte complet. ',80),
            'start_time'=>'2026-10-27 09:05:00','end_time'=>'2026-10-27 09:35:00']);
        $this->visit($user,['statut'=>'validee','date_activite'=>'2026-10-28']);
        $this->visit($user,['activity_type'=>'conge','date_fin'=>'2026-10-29']);
        $objective = Objectif::create(['user_id'=>$user->id,'date_debut'=>'2026-10-01','date_fin'=>'2026-10-31',
            'valeur_cible'=>10,'valeur_realisee'=>5,'statut'=>'actif']);
        TourPlan::create(['user_id'=>$user->id,'nom'=>'Semaine terrain','date_debut'=>'2026-10-26','date_fin'=>'2026-11-01','statut'=>'valide']);
        $response = $this->getJson('/api/v1/delegates/'.$user->id.'/overview')->assertOk()
            ->assertJsonCount(3,'visits')->assertJsonCount(1,'reports')->assertJsonPath('reports.0.id',$published->id)
            ->assertJsonPath('performance.planned_visits',3)->assertJsonPath('performance.completed_visits',2)
            ->assertJsonPath('performance.submitted_reports',1)->assertJsonPath('performance.pending_reports',1)
            ->assertJsonPath('performance.duration_sample_count',1)->assertJsonPath('performance.duration_sum_minutes',30)
            ->assertJsonPath('performance.timed_visits',1)->assertJsonPath('performance.on_time_visits',0)
            ->assertJsonPath('position.lat',6.1)->assertJsonPath('objectives.0.id',$objective->id)
            ->assertJsonCount(1,'weekly_plans')->assertJsonCount(1,'calendar');
        $response->assertDontSee('SECRET MOBILE DRAFT')->assertJsonMissingPath('visits.0.report_content');
        $this->assertSame(str_repeat('Texte complet. ',80),$response->json('reports.0.report_content'));
        $this->getJson('/api/v1/delegates/'.$user->id.'/overview?period=custom&start_date=2026-09-01&end_date=2026-09-30')
            ->assertOk()->assertJsonCount(0,'visits')->assertJsonCount(0,'reports')->assertJsonCount(0,'objectives');
    }

    public function test_profile_keeps_records_beyond_fifty_and_rejects_invalid_periods(): void
    {
        $user = $this->delegate();
        for ($i=0;$i<55;$i++) $this->visit($user,['report_status'=>'submitted','report_content'=>'Rapport '.$i]);
        $this->getJson('/api/v1/delegates/'.$user->id.'/overview')->assertOk()->assertJsonCount(55,'visits')->assertJsonCount(55,'reports');
        $this->getJson('/api/v1/delegates/'.$user->id.'/overview?period=custom')->assertUnprocessable();
    }

    public function test_country_options_include_empty_authorized_countries_and_empty_country_does_not_retain_data(): void
    {
        $this->delegate();
        $this->getJson('/api/v1/analytics/performance')->assertOk()->assertJsonCount(2,'meta.country_options');
        $response = $this->withHeader('X-Country-Id',$this->benin->id)->getJson('/api/v1/analytics/performance')->assertOk();
        $response->assertJsonCount(0,'data')->assertJsonCount(0,'planning')->assertJsonPath('meta.country_options.0.name','Bénin');
    }
    public function test_punctuality_uses_exact_recorded_time_including_sub_minute_delays(): void
    {
        $user=$this->delegate();
        $this->visit($user,['start_time'=>'2026-10-27 09:00:30','end_time'=>'2026-10-27 09:30:30']);
        $this->visit($user,['start_time'=>'2026-10-27 09:00:00','end_time'=>'2026-10-27 09:30:00']);
        $this->getJson('/api/v1/analytics/performance?user_id='.$user->id)->assertOk()
            ->assertJsonPath('data.0.timed_visits',2)->assertJsonPath('data.0.on_time_visits',1)
            ->assertJsonPath('data.0.duration_sum_minutes',60);
    }

    public function test_multi_practitioner_visits_keep_all_targets_in_metrics_and_profile(): void
    {
        $user=$this->delegate();
        $account=Account::create(['user_id'=>$user->id,'nom'=>'Clinique du Golfe']);
        $first=Contact::create(['user_id'=>$user->id,'prenom'=>'Afi','nom'=>'Mensah','account_id'=>$account->id]);
        $second=Contact::create(['user_id'=>$user->id,'prenom'=>'Koffi','nom'=>'Agbeko']);
        $visit=$this->visit($user,['contact_id'=>$first->id]);
        $visit->contacts()->sync([$first->id,$second->id]);
        $response=$this->getJson('/api/v1/delegates/'.$user->id.'/overview')->assertOk();
        $this->assertEqualsCanonicalizing([$first->id,$second->id],$response->json('planning.0.contact_ids'));
        $response->assertJsonPath('visits.0.doctorName','Afi Mensah, Koffi Agbeko')
            ->assertJsonPath('planning.0.account_id',$account->id)->assertJsonPath('visits.0.account.nom','Clinique du Golfe');
    }

}
