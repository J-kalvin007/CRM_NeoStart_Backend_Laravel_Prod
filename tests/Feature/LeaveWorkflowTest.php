<?php

namespace Tests\Feature;

use App\Mail\SkynityNotificationMail;
use App\Models\Activity;
use App\Models\Country;
use App\Models\Role;
use App\Models\TourPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private User $manager;

    private User $delegate;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        foreach (['superadmin', 'superviseur', 'delegue_medical'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->country = Country::create(['name' => 'Togo', 'iso_code' => 'TG']);
        $this->hr = $this->person('superadmin');
        $this->manager = $this->person('superviseur', ['manager_id' => $this->hr->id]);
        $this->delegate = $this->person('delegue_medical', ['manager_id' => $this->manager->id]);
    }

    private function person(string $role = 'delegue_medical', array $fields = []): User
    {
        $user = User::factory()->create(array_merge(['country_id' => $this->country->id], $fields));
        $user->assignRole($role);

        return $user;
    }

    private function credit(string $type = 'annual', int $days = 20): void
    {
        Sanctum::actingAs($this->hr);
        $this->postJson('/api/v1/leaves/balances', ['user_id' => $this->delegate->id, 'year' => now()->year, 'leave_type' => $type, 'remaining_days' => $days, 'reason' => 'Reprise du solde RH réel'])->assertOk();
    }

    private function payload(array $data = []): array
    {
        return array_merge(['leave_type' => 'annual', 'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(12)->toDateString(), 'return_date' => now()->addDays(13)->toDateString(), 'reference_year' => now()->year, 'reason' => 'Demande personnelle'], $data);
    }

    private function submit(array $data = []): string
    {
        Sanctum::actingAs($this->delegate);

        return $this->postJson('/api/v1/leaves', $this->payload($data))->assertCreated()->assertJsonPath('data.status', 'en_attente')->json('data.id');
    }

    public function test_mobile_to_web_sequential_workflow_debits_only_once_at_final_approval(): void
    {
        $this->credit();
        $id = $this->submit();
        $this->assertDatabaseHas('leave_balances', ['user_id' => $this->delegate->id, 'remaining_days' => 20]);
        Sanctum::actingAs($this->hr);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Trop tôt'])->assertForbidden();
        Sanctum::actingAs($this->manager);
        $this->getJson('/api/v1/leaves?pending_for_me=1')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve'])->assertUnprocessable();
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Continuité du secteur assurée'])->assertOk()->assertJsonPath('data.grant_status', 'pending');
        $this->assertDatabaseHas('leave_balances', ['user_id' => $this->delegate->id, 'remaining_days' => 20]);
        Sanctum::actingAs($this->hr);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Accord définitif RH'])->assertOk()->assertJsonPath('data.grant_status', 'granted')->assertJsonPath('data.status', 'validee');
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Doublon'])->assertForbidden();
        $this->assertDatabaseHas('leave_balances', ['user_id' => $this->delegate->id, 'remaining_days' => 17]);
        $this->assertDatabaseCount('leave_balance_entries', 2);
        Sanctum::actingAs($this->delegate);
        $this->getJson('/api/v1/leaves/'.$id)->assertOk()->assertJsonPath('data.workflow.2.status', 'completed');
        $this->getJson('/api/v1/leaves/options?year='.now()->year)->assertOk()->assertJsonPath('balances.0.remaining_days', 17);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->delegate->id]);
        Mail::assertSent(SkynityNotificationMail::class);
        $this->assertTrue(Activity::checkOverlap($this->delegate->id, $this->payload()['return_date'], '10:00', '11:00'));
    }

    public function test_rejection_and_cancellation_do_not_debit_and_cancellation_after_an_opinion_is_refused(): void
    {
        $this->credit();
        $id = $this->submit();
        $this->deleteJson('/api/v1/leaves/'.$id)->assertOk();
        $this->assertDatabaseHas('activities', ['id' => $id, 'statut' => 'annulee']);
        $id = $this->submit();
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'reject', 'motif' => 'Couverture insuffisante'])->assertOk();
        $this->assertDatabaseHas('leave_balances', ['user_id' => $this->delegate->id, 'remaining_days' => 20]);
        $id = $this->submit();
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Avis favorable'])->assertOk();
        Sanctum::actingAs($this->delegate);
        $this->deleteJson('/api/v1/leaves/'.$id)->assertUnprocessable();
    }

    public function test_no_fabricated_entitlement_sick_certificate_and_country_scoping(): void
    {
        Sanctum::actingAs($this->delegate);
        $this->getJson('/api/v1/leaves/options')->assertOk()->assertJsonCount(0, 'balances');
        $this->postJson('/api/v1/leaves', $this->payload())->assertUnprocessable();
        $this->credit('sick');
        Sanctum::actingAs($this->delegate);
        $this->postJson('/api/v1/leaves', $this->payload(['leave_type' => 'sick']))->assertUnprocessable();
        $id = $this->post('/api/v1/leaves', array_merge($this->payload(['leave_type' => 'sick']), ['attachments' => [UploadedFile::fake()->createWithContent('certificat.pdf', "%PDF-1.4\nCertificat")]]), ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $file = $this->getJson('/api/v1/leaves/'.$id)->assertOk()->json('data.documents.0.id');
        $url = '/api/v1/leaves/'.$id.'/documents/'.$file;
        $this->get($url)->assertOk();
        Sanctum::actingAs($this->person());
        $this->get($url)->assertNotFound();
        Sanctum::actingAs($this->hr);
        $bj = Country::create(['name' => 'Bénin', 'iso_code' => 'BJ']);
        $this->withHeader('X-Country-Id', $bj->id)->getJson('/api/v1/leaves')->assertOk()->assertJsonCount(0, 'data');
        $this->get($url)->assertNotFound();
    }

    public function test_pending_overlaps_return_day_and_generic_crm_endpoints_cannot_bypass_rh(): void
    {
        $this->credit();
        $id = $this->submit();
        $this->postJson('/api/v1/leaves', $this->payload(['start_date' => $this->payload()['return_date'], 'end_date' => now()->addDays(15)->toDateString(), 'return_date' => now()->addDays(16)->toDateString()]))->assertUnprocessable();
        $this->putJson('/api/v1/activities/'.$id, ['notes' => 'Altération'])->assertUnprocessable();
        $this->deleteJson('/api/v1/activities/'.$id)->assertUnprocessable();
        $this->assertDatabaseHas('activities', ['id' => $id, 'statut' => 'en_attente']);
    }

    public function test_balance_is_rechecked_at_final_approval_and_reliquats_are_traced(): void
    {
        $this->credit();
        $id = $this->submit();
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Avis favorable'])->assertOk();
        $this->credit('annual', 1);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Accord RH'])->assertUnprocessable();
        $this->assertDatabaseHas('leave_requests', ['activity_id' => $id, 'grant_status' => 'pending', 'debited_at' => null]);
        $this->credit('annual', 20);
        $this->postJson('/api/v1/leaves/carry-over', ['user_id' => $this->delegate->id, 'source_year' => now()->year, 'target_year' => now()->year + 1, 'days' => 5, 'reason' => 'Report du reliquat autorisé'])->assertOk();
        $this->assertDatabaseHas('leave_balances', ['user_id' => $this->delegate->id, 'year' => now()->year, 'remaining_days' => 15]);
        $this->assertDatabaseHas('leave_balances', ['user_id' => $this->delegate->id, 'year' => now()->year + 1, 'remaining_days' => 5]);
    }

    public function test_configured_exceptional_barreme_and_hierarchy_cycles_are_enforced(): void
    {
        $this->credit('exceptional');
        Sanctum::actingAs($this->hr);
        $this->putJson('/api/v1/leaves/policy', ['country_id' => $this->country->id, 'permission_rules' => [['code' => 'marriage', 'label' => 'Mariage', 'days' => 3]], 'absence_codes' => ['exceptional' => 'PERM']])->assertOk();
        $id = $this->submit(['leave_type' => 'exceptional', 'permission_code' => 'marriage']);
        $this->getJson('/api/v1/leaves/'.$id)->assertOk()->assertJsonPath('data.absence_code', 'PERM');
        $this->deleteJson('/api/v1/leaves/'.$id)->assertOk();
        $this->delegate->update(['manager_id' => $this->manager->id]);
        $this->manager->update(['manager_id' => $this->delegate->id]);
        $this->postJson('/api/v1/leaves', $this->payload(['leave_type' => 'exceptional', 'permission_code' => 'marriage']))->assertUnprocessable();
        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_weekly_approval_cannot_approve_a_rh_absence_and_sync_keeps_its_contract(): void
    {
        $this->credit();
        $id = $this->submit();
        $plan = TourPlan::create(['user_id' => $this->delegate->id, 'nom' => 'Semaine', 'date_debut' => $this->payload()['start_date'], 'date_fin' => $this->payload()['end_date'], 'statut' => 'soumis']);
        Activity::withoutGlobalScopes()->whereKey($id)->update(['tour_plan_id' => $plan->id]);
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/v1/validations/tour_plan/'.$plan->id.'/approve')->assertOk();
        $this->assertDatabaseHas('activities', ['id' => $id, 'statut' => 'en_attente']);
        $this->assertDatabaseHas('leave_balances', ['user_id' => $this->delegate->id, 'remaining_days' => 20]);
        Sanctum::actingAs($this->delegate);
        $response = $this->getJson('/api/v1/sync/pull')->assertOk();
        $leaves = $response->json('data.leaves') ?? $response->json('leaves');
        $this->assertNotNull($leaves);
        $this->assertTrue(collect($leaves)->firstWhere('id', $id)['workflow_managed']);
    }

    public function test_calendar_has_real_rh_and_historical_absences_and_maternity_needs_verified_eligibility(): void
    {
        $this->credit();
        $id = $this->submit();
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Avis favorable'])->assertOk();
        Sanctum::actingAs($this->hr);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Accord final'])->assertOk();
        Activity::create(['user_id' => $this->delegate->id, 'activity_type' => 'permission', 'statut' => 'validee', 'date_activite' => now()->addDays(20)->toDateString()]);
        $response = $this->getJson('/api/v1/leaves/calendar?start='.now()->toDateString().'&end='.now()->addDays(30)->toDateString())->assertOk()->assertJsonCount(2, 'data');
        $this->assertEquals('#059669', collect($response->json('data'))->firstWhere('id', $id)['color']);
        $this->postJson('/api/v1/leaves/balances', ['user_id' => $this->delegate->id, 'year' => now()->year, 'leave_type' => 'maternity', 'remaining_days' => 98, 'reason' => 'Éligibilité non vérifiée'])->assertUnprocessable();
        $this->postJson('/api/v1/leaves/balances', ['user_id' => $this->delegate->id, 'year' => now()->year, 'leave_type' => 'maternity', 'remaining_days' => 98, 'reason' => 'Éligibilité vérifiée par RH', 'maternity_eligible' => true])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $this->delegate->id, 'maternity_leave_eligible' => true]);
    }

    public function test_functional_manager_and_hr_pool_are_not_replaced_by_arbitrary_managers(): void
    {
        $functional = $this->person('superviseur');
        $this->delegate->update(['manager_fonctionnel_id' => $functional->id]);
        $this->credit();
        $id = $this->submit();
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Avis N+1 favorable'])->assertOk();
        Sanctum::actingAs($this->hr);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Étape sautée'])->assertForbidden();
        Sanctum::actingAs($functional);
        $this->getJson('/api/v1/leaves?pending_for_me=1')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Avis fonctionnel favorable'])->assertOk();
        Sanctum::actingAs($this->hr);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Accord final'])->assertOk();
    }

    public function test_new_absences_cannot_bypass_rh_through_old_creation_or_bulk_endpoints(): void
    {
        Sanctum::actingAs($this->delegate);
        $this->postJson('/api/v1/leaves', ['type' => 'conge', 'start_date' => $this->payload()['start_date'], 'end_date' => $this->payload()['end_date'], 'reason' => 'Ancien contrat'])->assertUnprocessable();
        $this->postJson('/api/v1/activities', ['activity_type' => 'conge', 'date_activite' => $this->payload()['start_date'], 'date_fin' => $this->payload()['end_date'], 'heure_debut' => '09:00', 'heure_fin' => '18:00'])->assertUnprocessable();
        $this->credit();
        $id = $this->submit();
        $this->postJson('/api/v1/activities/bulk-delete', ['activity_ids' => [$id]])->assertUnprocessable();
        $other = $this->person();
        $visit = Activity::create(['user_id' => $other->id, 'activity_type' => 'visite', 'date_activite' => $this->payload()['start_date'], 'statut' => 'en_attente']);
        $this->postJson('/api/v1/activities/bulk-delete', ['activity_ids' => [$visit->id]])->assertForbidden();
        $this->postJson('/api/v1/activities/bulk-validate', ['activity_ids' => [$visit->id], 'statut' => 'validee'])->assertForbidden();
        $this->assertDatabaseHas('activities', ['id' => $id, 'deleted_at' => null]);
    }

    public function test_bulk_visit_decision_cannot_change_a_submitted_report_or_partially_update_a_batch(): void
    {
        $done = Activity::create(['user_id' => $this->delegate->id, 'activity_type' => 'visite', 'date_activite' => $this->payload()['start_date'], 'statut' => 'realisee', 'report_status' => 'submitted']);
        $pending = Activity::create(['user_id' => $this->delegate->id, 'activity_type' => 'visite', 'date_activite' => $this->payload()['start_date'], 'statut' => 'en_attente']);
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/v1/activities/bulk-validate', ['activity_ids' => [$pending->id, $done->id], 'statut' => 'validee'])->assertUnprocessable();
        $this->assertDatabaseHas('activities', ['id' => $pending->id, 'statut' => 'en_attente']);
        $this->assertDatabaseHas('activities', ['id' => $done->id, 'statut' => 'realisee', 'report_status' => 'submitted']);
    }

    public function test_final_approval_permission_is_available_to_real_roles_and_not_inferred_from_a_title(): void
    {
        $this->credit();
        $this->getJson('/api/v1/permissions')->assertOk()->assertJsonFragment(['name' => 'leaves.approve-final']);
        $this->manager->givePermissionTo('leaves.approve-final');
        $id = $this->submit();
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/v1/leaves/'.$id.'/decision', ['decision' => 'approve', 'motif' => 'Accord final avec habilitation RH'])->assertOk()->assertJsonPath('data.grant_status', 'granted');
        $this->assertDatabaseHas('leave_balances', ['user_id' => $this->delegate->id, 'remaining_days' => 17]);
    }
    public function test_missing_final_decider_in_the_chain_routes_to_the_authorized_hr_pool(): void
    {
        $this->manager->update(['manager_id'=>null]);$this->credit();$id=$this->submit();
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/v1/leaves/'.$id.'/decision',['decision'=>'approve','motif'=>'Avis du supérieur'])->assertOk();
        $bj=Country::create(['name'=>'Bénin','iso_code'=>'BJ']);Role::firstOrCreate(['name'=>'rh','guard_name'=>'web']);
        $foreign=$this->person('rh',['country_id'=>$bj->id]);Sanctum::actingAs($foreign);
        $this->postJson('/api/v1/leaves/'.$id.'/decision',['decision'=>'approve','motif'=>'Hors périmètre'])->assertNotFound();
        Sanctum::actingAs($this->hr);
        $this->getJson('/api/v1/leaves?pending_for_me=1')->assertOk()->assertJsonPath('data.0.id',$id);
        $this->postJson('/api/v1/leaves/'.$id.'/decision',['decision'=>'approve','motif'=>'Accord final du groupe RH'])->assertOk()->assertJsonPath('data.grant_status','granted');
    }
    public function test_territorial_warning_measures_simultaneous_absence_instead_of_all_people_absent_during_the_period(): void
    {
        $post=\App\Models\FieldPost::create(['country_id'=>$this->country->id,'name'=>'Poste réel']);
        $this->delegate->update(['field_post_id'=>$post->id]);
        $first=$this->person('delegue_medical',['field_post_id'=>$post->id]);
        $second=$this->person('delegue_medical',['field_post_id'=>$post->id]);
        Activity::create(['user_id'=>$first->id,'activity_type'=>'permission','statut'=>'validee','date_activite'=>$this->payload()['start_date'],'date_fin'=>now()->addDays(11)->toDateString()]);
        Activity::create(['user_id'=>$second->id,'activity_type'=>'permission','statut'=>'validee','date_activite'=>now()->addDays(12)->toDateString(),'date_fin'=>$this->payload()['return_date']]);
        $this->credit();$id=$this->submit();
        $this->getJson('/api/v1/leaves/'.$id)->assertOk()->assertJsonPath('data.coverage_warning.team_size',3)
            ->assertJsonPath('data.coverage_warning.simultaneously_absent',2)->assertJsonPath('data.coverage_warning.warning',true);
    }
}
