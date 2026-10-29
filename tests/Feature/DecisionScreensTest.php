<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Country;
use App\Models\Product;
use App\Models\Role;
use App\Models\SampleMovement;
use App\Models\User;
use App\Models\UserLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DecisionScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Country $tg;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        foreach (['superadmin', 'superviseur', 'delegue_medical'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->tg = Country::create(['name' => 'Togo', 'iso_code' => 'TG']);
        $this->admin = User::factory()->create(['country_id' => $this->tg->id]);
        $this->admin->assignRole('superadmin');
        Sanctum::actingAs($this->admin);
    }

    private function person(array $fields = []): User
    {
        $u = User::factory()->create(array_merge(['country_id' => $this->tg->id], $fields));
        $u->assignRole('delegue_medical');

        return $u;
    }

    private function visit(User $u, array $fields = []): Activity
    {
        return Activity::create(array_merge(['user_id' => $u->id, 'activity_type' => 'visite', 'statut' => 'realisee',
            'date_activite' => now()->toDateString(), 'heure_debut' => '09:00'], $fields));
    }

    public function test_executive_excludes_leave_and_compares_the_same_length_previous_period(): void
    {
        $u = $this->person();
        $this->visit($u);
        $this->visit($u, ['activity_type' => 'conge']);
        $this->visit($u, ['statut' => 'brouillon']);
        $day = now()->toDateString();
        $this->getJson('/api/v1/analytics/executive?period=custom&start_date='.$day.'&end_date='.$day)->assertOk()
            ->assertJsonPath('total_visits', 1)->assertJsonPath('summary.completed_visits', 1)
            ->assertJsonPath('comparison.start_date', now()->subDay()->toDateString());
        $this->getJson('/api/v1/reports/overview?kind=planning')->assertOk()->assertJsonPath('module', 'planningExecution');
    }

    public function test_hierarchy_filters_are_scoped_and_unknown_managers_are_rejected(): void
    {
        $manager = $this->person();
        $other = $this->person();
        $u = $this->person(['manager_id' => $manager->id]);
        $this->visit($u);
        $this->visit($other);
        $this->getJson('/api/v1/analytics/performance?manager_id='.$manager->id)->assertOk()->assertJsonCount(2, 'data');
        Sanctum::actingAs($u);
        $this->getJson('/api/v1/analytics/performance?manager_id='.$other->id)->assertUnprocessable();
    }

    public function test_itinerary_uses_actual_points_and_does_not_expose_other_teams(): void
    {
        $u = $this->person();
        $this->visit($u);
        foreach ([[6.1, 1.2], [6.2, 1.3]] as $i => [$lat, $lng]) {
            UserLocation::create(['user_id' => $u->id, 'lat' => $lat, 'lng' => $lng, 'recorded_at' => now()->startOfDay()->addHours(9 + $i)]);
        }
        $response = $this->getJson('/api/v1/delegates/'.$u->id.'/itinerary')->assertOk()->assertJsonCount(2, 'points')->assertJsonCount(1, 'visits');
        $this->assertGreaterThan(0, $response->json('summary.distance_km_estimated'));
        Sanctum::actingAs($this->person());
        $this->getJson('/api/v1/delegates/'.$u->id.'/itinerary')->assertForbidden();
    }

    public function test_compliance_is_repeatable_private_and_decisions_require_an_audit_note(): void
    {
        $manager = $this->person();
        $manager->assignRole('superviseur');
        $u = $this->person(['manager_id' => $manager->id]);
        $p = Product::create(['nom' => 'Stock réel', 'code' => 'S-REAL']);
        SampleMovement::create(['user_id' => $u->id, 'product_id' => $p->id, 'movement_type' => 'distribution', 'quantity' => 3, 'stock_apres' => 0]);
        Sanctum::actingAs($manager);
        $id = $this->getJson('/api/v1/compliance/anomalies')->assertOk()->assertJsonCount(1)->json('0.id');
        $this->getJson('/api/v1/compliance/anomalies')->assertJsonCount(1);
        $this->postJson('/api/v1/compliance/anomalies/'.$id.'/resolve')->assertUnprocessable();
        $this->postJson('/api/v1/compliance/anomalies/'.$id.'/resolve', ['note' => 'Registre corrigé après inventaire'])->assertOk();
        $this->assertDatabaseCount('compliance_decisions', 1);
        Sanctum::actingAs($u);
        $this->postJson('/api/v1/compliance/anomalies/'.$id.'/resolve', ['note' => 'Auto validation'])->assertForbidden();
        $bj = Country::create(['name' => 'Bénin', 'iso_code' => 'BJ']);
        Sanctum::actingAs($this->admin);
        $this->withHeader('X-Country-Id', $bj->id)->getJson('/api/v1/compliance/anomalies')->assertOk()->assertExactJson([]);
    }

    public function test_groups_and_private_attachments_preserve_participant_access(): void
    {
        Storage::fake('local');
        $a = $this->person();
        $b = $this->person();
        $id = $this->postJson('/api/v1/conversations', ['recipient_ids' => [$a->id, $b->id], 'type' => 'group', 'title' => 'Équipe terrain'])->assertCreated()->json('data.id');
        $this->post('/api/v1/conversations/'.$id.'/messages', ['content' => 'Document de terrain', 'attachments' => [UploadedFile::fake()->createWithContent('visites.txt', 'Texte réel')]], ['Accept' => 'application/json'])->assertCreated();
        $rows = $this->getJson('/api/v1/conversations/'.$id.'/messages')->assertOk()->json('data');
        $url = $rows[0]['attachments'][0]['url'];
        Sanctum::actingAs($a);
        $this->get($url)->assertOk();
        Sanctum::actingAs($this->person());
        $this->get($url)->assertForbidden();
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/v1/conversations/'.$id.'/participants', ['recipient_ids' => [$b->id]])->assertOk();
        Sanctum::actingAs($a);
        $this->get($url)->assertForbidden();
    }

    public function test_announcements_are_read_only_for_recipients(): void
    {
        $a = $this->person();
        $id = $this->postJson('/api/v1/conversations', ['recipient_id' => $a->id, 'type' => 'announcement', 'title' => 'Consigne', 'message' => 'Réunion terrain'])->assertCreated()->json('data.id');
        Sanctum::actingAs($a);
        $this->postJson('/api/v1/conversations/'.$id.'/messages', ['content' => 'Réponse'])->assertForbidden();
    }

    public function test_field_posts_are_country_safe_and_filter_actual_assignments(): void
    {
        $bj = Country::create(['name' => 'Bénin', 'iso_code' => 'BJ']);
        $u = $this->person();
        $id = $this->postJson('/api/v1/field-posts', ['country_id' => $this->tg->id, 'name' => 'Lomé Centre'])->assertCreated()->json('data.id');
        $other = $this->postJson('/api/v1/field-posts', ['country_id' => $bj->id, 'name' => 'Cotonou'])->assertCreated()->json('data.id');
        $this->putJson('/api/v1/users/'.$u->id, ['field_post_id' => $other])->assertUnprocessable();
        $this->putJson('/api/v1/users/'.$u->id, ['field_post_id' => $id])->assertOk()->assertJsonPath('data.field_post_id', $id);
        $this->getJson('/api/v1/analytics/performance?field_post_id='.$id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.field_post_name', 'Lomé Centre');
        $this->deleteJson('/api/v1/field-posts/'.$id)->assertStatus(409);
        $this->withHeader('X-Country-Id', $bj->id)->getJson('/api/v1/field-posts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other);
        $this->withHeader('X-Country-Id', 'group')->putJson('/api/v1/users/'.$u->id, ['country_id' => $bj->id])->assertOk()->assertJsonPath('data.field_post_id', null);
        Sanctum::actingAs($u);
        $this->postJson('/api/v1/field-posts', ['country_id' => $bj->id, 'name' => 'Interdit'])->assertForbidden();
    }

    public function test_reports_hub_preserves_draft_privacy_and_target_filter(): void
    {
        $u = $this->person();
        $this->visit($u, ['report_status' => 'draft', 'report_content' => 'Contenu privé']);
        $this->visit($u, ['report_status' => 'submitted', 'report_content' => 'Contenu soumis']);
        $this->getJson('/api/v1/reports/overview?kind=reports')->assertOk()->assertJsonCount(1, 'rows')->assertDontSee('Contenu privé');
        $this->getJson('/api/v1/reports/overview?kind=visits&target_type=practitioner')->assertOk()->assertJsonCount(0, 'rows');
        $this->getJson('/api/v1/delegates/'.$u->id.'/itinerary')->assertOk()->assertJsonPath('summary.distance_km_estimated', null);
    }

    public function test_compliance_explanation_has_owner_response_and_audit_history(): void
    {
        $u = $this->person();
        $p = Product::create(['nom' => 'Stock suivi', 'code' => 'S-AUDIT']);
        SampleMovement::create(['user_id' => $u->id, 'product_id' => $p->id, 'movement_type' => 'distribution', 'quantity' => 2, 'stock_apres' => 0]);
        $id = $this->getJson('/api/v1/compliance/anomalies')->assertOk()->json('0.id');
        $this->postJson('/api/v1/compliance/anomalies/'.$id.'/request-explanation', ['note' => 'Merci de justifier cette sortie'])->assertOk();
        Sanctum::actingAs($u);
        $this->postJson('/api/v1/compliance/anomalies/'.$id.'/respond', ['note' => 'Bon de sortie transmis au responsable'])->assertOk()->assertJsonPath('status', 'EN_ATTENTE');
        $this->assertDatabaseCount('compliance_decisions', 2);
        $this->postJson('/api/v1/compliance/anomalies/'.$id.'/respond', ['note' => 'Seconde réponse'])->assertForbidden();
        $this->patchJson('/api/v1/compliance/rules', ['short_visit_minutes' => 10, 'gps_tolerance_meters' => 100])->assertForbidden();
    }

    public function test_group_reading_does_not_clear_another_participants_unread_messages(): void
    {
        $a = $this->person();
        $b = $this->person();
        $id = $this->postJson('/api/v1/conversations', ['recipient_ids' => [$a->id, $b->id], 'type' => 'group', 'title' => 'Lectures indépendantes', 'message' => 'Consigne'])->assertCreated()->json('data.id');
        Sanctum::actingAs($a);
        $this->getJson('/api/v1/conversations/'.$id.'/messages')->assertOk();
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 0);
        Sanctum::actingAs($b);
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 1);
    }
}
