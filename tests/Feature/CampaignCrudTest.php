<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampaignCrudTest extends TestCase
{
    use RefreshDatabase;

    private function country(): Country
    {
        return Country::first() ?? Country::create([
            'name' => 'Togo',
            'iso_code' => 'TG',
            'currency' => 'XOF',
            'language' => 'fr',
            'status' => true,
        ]);
    }

    public function test_campaign_can_be_created_with_brouillon_status_and_explicit_country(): void
    {
        $country = $this->country();
        $user = User::factory()->create(['country_id' => $country->id]);
        Sanctum::actingAs($user);

        $payload = [
            'country_id' => $country->id,
            'nom' => 'Campagne Cardiologie 2026',
            'date_debut' => '2026-11-01',
            'date_fin' => '2026-12-31',
            'budget' => 500000,
            'statut' => 'brouillon',
            'meta' => ['type_campagne' => 'Lancement'],
            'message' => 'Lancement produit cardiovasculaire.',
        ];

        $response = $this->postJson('/api/v1/campaigns', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('campaigns', [
            'nom' => 'Campagne Cardiologie 2026',
            'country_id' => $country->id,
            'statut' => 'brouillon',
        ]);
    }

    public function test_campaign_creation_falls_back_to_country_filter_when_omitted(): void
    {
        $country = $this->country();
        $user = User::factory()->create(['country_id' => $country->id]);
        Sanctum::actingAs($user);

        $payload = [
            'nom' => 'Campagne Sans Country Explicite',
            'date_debut' => '2026-11-01',
            'date_fin' => '2026-12-31',
            'budget' => 250000,
            'statut' => 'brouillon',
        ];

        $response = $this->withHeaders(['X-Country-Id' => $country->id])
            ->postJson('/api/v1/campaigns', $payload);

        $response->assertCreated();
        $this->assertEquals($country->id, $response->json('country_id'));
    }

    public function test_campaign_status_transition(): void
    {
        $country = $this->country();
        $user = User::factory()->create(['country_id' => $country->id]);
        Sanctum::actingAs($user);

        $campaign = Campaign::create([
            'country_id' => $country->id,
            'nom' => 'Campagne Transition Statut',
            'date_debut' => '2026-11-01',
            'date_fin' => '2026-12-31',
            'statut' => 'brouillon',
        ]);

        $response = $this->patchJson("/api/v1/campaigns/{$campaign->id}/status", [
            'statut' => 'validation',
        ]);

        $response->assertOk();
        $this->assertEquals('validation', $campaign->fresh()->statut);
    }
}
