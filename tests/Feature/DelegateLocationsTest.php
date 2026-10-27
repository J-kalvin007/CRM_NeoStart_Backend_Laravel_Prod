<?php

namespace Tests\Feature;

use App\Models\{Activity, Country, Role, User, UserLocation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DelegateLocationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Country $togo;
    private Country $benin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfDay()->setHour(12));
        foreach (['superadmin', 'superviseur', 'delegue-medical', 'delegue_medical'] as $role) Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $this->togo = Country::create(['name' => 'Togo', 'iso_code' => 'TG']);
        $this->benin = Country::create(['name' => 'Bénin', 'iso_code' => 'BJ']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');
        Sanctum::actingAs($this->admin);
    }

    private function delegate(array $attributes = [], string $role = 'delegue-medical'): User
    {
        $user = User::factory()->create(array_merge(['country_id' => $this->togo->id], $attributes));
        $user->assignRole($role);
        return $user;
    }

    public function test_global_admin_sees_named_delegates_without_a_direct_team_and_country_filter_stays_strict(): void
    {
        $togo = $this->delegate(['name' => 'Afi Mensah', 'last_lat' => 6.13, 'last_lng' => 1.21, 'last_location_at' => now()->subMinutes(3)]);
        $benin = $this->delegate(['name' => 'Koffi Agbeko', 'country_id' => $this->benin->id], 'delegue_medical');
        $this->getJson('/api/v1/users/locations')->assertOk()->assertJsonCount(2)
            ->assertJsonFragment(['id' => $togo->id, 'name' => 'Afi Mensah']);
        $this->withHeader('X-Country-Id', $this->benin->id)->getJson('/api/v1/users/locations')->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.id', $benin->id)->assertJsonPath('0.country_name', 'Bénin');
        $this->withHeader('X-Country-Id', 'unknown')->getJson('/api/v1/users/locations')->assertOk()->assertExactJson([]);
    }

    public function test_manager_only_sees_delegate_descendants_and_a_delegate_only_sees_themself(): void
    {
        $manager = User::factory()->create(); $manager->assignRole('superviseur');
        $nested = User::factory()->create(['manager_id' => $manager->id]); $nested->assignRole('superviseur');
        $own = $this->delegate(['manager_id' => $manager->id]);
        $descendant = $this->delegate(['manager_id' => $nested->id]);
        $other = $this->delegate();
        Sanctum::actingAs($manager);
        $response = $this->getJson('/api/v1/users/locations')->assertOk()->assertJsonCount(2);
        $this->assertEqualsCanonicalizing([$own->id, $descendant->id], array_column($response->json(), 'id'));
        $response->assertJsonMissing(['id' => $other->id])->assertJsonMissing(['id' => $nested->id]);
        Sanctum::actingAs($own);
        $this->getJson('/api/v1/users/locations')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $own->id);
    }

    public function test_live_position_and_its_real_timestamp_are_returned_with_the_name(): void
    {
        $delegate = $this->delegate(['name' => 'Afi Mensah', 'last_lat' => 6.12, 'last_lng' => 1.23,
            'last_accuracy' => 8, 'last_location_at' => now()->subMinutes(5)]);
        $response = $this->getJson('/api/v1/users/locations')->assertOk()
            ->assertJsonPath('0.name', 'Afi Mensah')->assertJsonPath('0.has_position', true)
            ->assertJsonPath('0.is_live', true)->assertJsonPath('0.position_source', 'gps_live');
        $this->assertSame(6.12, $response->json('0.lat'));
        $this->assertSame(1.23, $response->json('0.lng'));
        $this->assertSame($delegate->last_location_at->toISOString(), $response->json('0.last_seen'));
    }

    public function test_missing_or_partial_gps_keeps_the_delegate_in_the_list_without_inventing_a_position(): void
    {
        $this->delegate(['name' => 'Sans GPS', 'last_lat' => null, 'last_lng' => null, 'last_login_at' => now()]);
        $this->delegate(['name' => 'GPS incomplet', 'last_lat' => 6.1, 'last_lng' => null, 'last_location_at' => now()]);
        foreach ($this->getJson('/api/v1/users/locations')->assertOk()->assertJsonCount(2)->json() as $row) {
            $this->assertFalse($row['has_position']); $this->assertFalse($row['is_live']);
            $this->assertNull($row['lat']); $this->assertNull($row['lng']); $this->assertNull($row['last_seen']);
        }
    }

    public function test_latest_recorded_mobile_position_is_recovered_when_user_summary_is_missing(): void
    {
        $delegate = $this->delegate();
        foreach ([20, 4] as $minutes) UserLocation::create(['user_id' => $delegate->id, 'lat' => 6.1 + $minutes / 100, 'lng' => 1.2, 'recorded_at' => now()->subMinutes($minutes)]);
        $this->getJson('/api/v1/users/locations')->assertOk()->assertJsonPath('0.lat', 6.14)
            ->assertJsonPath('0.position_source', 'gps_live')->assertJsonPath('0.is_live', true);
    }

    public function test_visit_checkout_is_used_without_treating_report_edits_as_new_gps_signals(): void
    {
        $delegate = $this->delegate(['last_lat' => 6.01, 'last_lng' => 1.01, 'last_location_at' => now()->subDays(3)]);
        Activity::create(['user_id' => $delegate->id, 'activity_type' => 'visite', 'statut' => 'realisee',
            'report_status' => 'draft', 'report_content' => 'PRIVE', 'date_activite' => now()->subDay()->toDateString(),
            'gps_debut_lat' => 6.1, 'gps_debut_lng' => 1.1, 'start_time' => now()->subDay()->setHour(9),
            'gps_fin_lat' => 6.2, 'gps_fin_lng' => 1.2, 'end_time' => now()->subDay()->setHour(10)]);
        $this->getJson('/api/v1/users/locations')->assertOk()->assertJsonPath('0.lat', 6.2)
            ->assertJsonPath('0.position_source', 'check_out')->assertJsonPath('0.is_live', false)
            ->assertJsonPath('0.last_seen', now()->subDay()->setHour(10)->toISOString())->assertJsonMissing(['report_content' => 'PRIVE']);
    }

    public function test_old_mobile_positions_are_not_presented_as_live_and_zero_latitude_is_valid(): void
    {
        $this->delegate(['last_lat' => 0, 'last_lng' => 1.2, 'last_location_at' => now()->subHours(2)]);
        $this->getJson('/api/v1/users/locations')->assertOk()->assertJsonPath('0.lat', 0)
            ->assertJsonPath('0.has_position', true)->assertJsonPath('0.is_live', false);
    }

    public function test_fresh_mobile_signal_wins_over_an_older_visit_and_no_draft_report_is_exposed(): void
    {
        $delegate = $this->delegate(['last_lat' => 6.31, 'last_lng' => 1.41, 'last_location_at' => now()->subMinute()]);
        Activity::create(['user_id' => $delegate->id, 'activity_type' => 'visite', 'statut' => 'en_cours',
            'date_activite' => now()->toDateString(), 'gps_debut_lat' => 6.1, 'gps_debut_lng' => 1.1,
            'start_time' => now()->subHour(), 'report_status' => 'draft', 'report_content' => 'PRIVE']);
        $response = $this->getJson('/api/v1/users/locations')->assertOk()->assertJsonPath('0.lat', 6.31)
            ->assertJsonPath('0.status', 'en_visite')->assertJsonPath('0.is_live', true);
        $this->assertStringNotContainsString('PRIVE', $response->getContent());
    }

    public function test_mobile_location_upload_becomes_the_named_position_seen_by_the_web(): void
    {
        $delegate = $this->delegate(['name' => 'Délégué terrain']);
        Sanctum::actingAs($delegate);
        $this->postJson('/api/v1/me/location', ['lat' => 6.32, 'lng' => 1.25, 'accuracy' => 4,
            'recorded_at' => now()->subMinutes(2)->toISOString()])->assertCreated();
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/users/locations')->assertOk()->assertJsonPath('0.name', 'Délégué terrain')
            ->assertJsonPath('0.lat', 6.32)->assertJsonPath('0.lng', 1.25)
            ->assertJsonPath('0.position_source', 'gps_live')->assertJsonPath('0.is_live', true);
        $this->assertDatabaseCount('user_locations', 1);
    }
}
