<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Region;
use App\Models\Sector;
use App\Models\Subsidiary;
use App\Models\Team;
use App\Models\Territory;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamApiResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_teams_api_returns_name_and_sector_id_for_frontend(): void
    {
        $admin = User::factory()->create();

        $country = Country::create([
            'name' => 'France',
            'iso_code' => 'FR',
            'currency' => 'EUR',
            'language' => 'fr',
            'timezone' => 'Europe/Paris',
            'status' => true,
        ]);

        $subsidiary = Subsidiary::create([
            'country_id' => $country->id,
            'name' => 'Filiale Paris',
            'code' => 'SUB-001',
            'status' => true,
        ]);

        $region = Region::create([
            'subsidiary_id' => $subsidiary->id,
            'name' => 'Île-de-France',
        ]);

        $territory = Territory::create([
            'region_id' => $region->id,
            'name' => 'Paris',
        ]);

        $zone = Zone::create([
            'territory_id' => $territory->id,
            'name' => 'Paris Centre',
            'code' => 'Z-Paris',
            'is_active' => true,
        ]);

        $sector = Sector::create([
            'zone_id' => $zone->id,
            'name' => 'Secteur Central',
            'code' => 'SEC-001',
            'is_active' => true,
        ]);

        Team::create([
            'name' => 'Équipe Nord',
            'manager_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/teams');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Équipe Nord']);
        $response->assertJsonPath('data.0.manager_id', $admin->id);
    }

    public function test_team_manager_can_be_updated_via_api(): void
    {
        $admin = User::factory()->create();
        $manager = User::factory()->create();

        $country = Country::create([
            'name' => 'France',
            'iso_code' => 'US',
            'currency' => 'EUR',
            'language' => 'fr',
            'timezone' => 'Europe/Paris',
            'status' => true,
        ]);

        $subsidiary = Subsidiary::create([
            'country_id' => $country->id,
            'name' => 'Filiale Paris',
            'code' => 'SUB-002',
            'status' => true,
        ]);

        $team = Team::create([
            'name' => 'Équipe Sud',
            'manager_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->putJson('/api/v1/teams/' . $team->id, [
            'name' => 'Équipe Sud',
            'manager_id' => $manager->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'manager_id' => $manager->id,
        ]);
    }
}
