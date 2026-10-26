<?php
namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubmittedReportsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_only_transmitted_reports_and_cannot_read_another_users_draft(): void
    {
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $delegate = User::factory()->create();
        $reports = [];
        foreach (['draft', 'submitted', 'validated', 'rejected'] as $status) {
            $reports[$status] = Activity::create(['user_id' => $delegate->id, 'statut' => 'realisee',
                'date_activite' => now()->toDateString(), 'activity_type' => 'visite',
                'report_status' => $status, 'report_content' => 'Rapport ' . $status]);
        }
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/activities?reports_only=1')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonMissing(['id' => $reports['draft']->id]);
        $this->getJson('/api/v1/activities?reports_only=1&report_status=draft')->assertOk()->assertJsonCount(0, 'data');
        $response = $this->getJson('/api/v1/activities')->assertOk();
        $draft = collect($response->json('data'))->firstWhere('id', $reports['draft']->id);
        $this->assertNotNull($draft, 'The visit remains visible for planning.');
        $this->assertNull($draft['report_content']);
        $this->getJson('/api/v1/activities/' . $reports['draft']->id)->assertOk()->assertJsonPath('data.report_content', null);
        $this->assertSame('Rapport draft', $reports['draft']->fresh()->getRawOriginal('report_content'));

        Sanctum::actingAs($delegate);
        $this->getJson('/api/v1/activities?reports_only=1')->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/v1/activities/' . $reports['draft']->id)->assertOk()->assertJsonPath('data.report_content', 'Rapport draft');
    }

    public function test_published_reports_and_statistics_do_not_mix_countries(): void
    {
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $countries = [Country::create(['name' => 'Togo', 'iso_code' => 'TG']), Country::create(['name' => 'Bénin', 'iso_code' => 'BJ'])];
        $visits = [];
        foreach ($countries as $i => $country) {
            $user = User::factory()->create(['country_id' => $country->id]);
            $visits[] = Activity::create(['user_id' => $user->id, 'statut' => 'realisee', 'activity_type' => 'visite',
                'date_activite' => now()->toDateString(), 'report_status' => 'submitted', 'report_content' => 'Rapport pays ' . $i]);
        }
        Sanctum::actingAs($admin);
        foreach ($countries as $i => $country) {
            $this->withHeader('X-Country-Id', $country->id)->getJson('/api/v1/activities?reports_only=1')->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visits[$i]->id);
            $this->getJson('/api/v1/analytics/executive')->assertOk()->assertJsonPath('total_visits', 1);
            $this->getJson('/api/v1/activities/' . $visits[1 - $i]->id)->assertNotFound();
        }
    }
}
