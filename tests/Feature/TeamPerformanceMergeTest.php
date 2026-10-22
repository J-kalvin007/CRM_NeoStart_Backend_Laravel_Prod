<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamPerformanceMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_performance_works_without_removed_sector_relation(): void
    {
        $manager = User::factory()->create();
        $delegate = User::factory()->create(['manager_id' => $manager->id]);
        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/analytics/team-performance?period=monthly');
        $response->assertOk();
        $entry = collect($response->json('data'))->firstWhere('delegate.id', $delegate->id);
        $this->assertNotNull($entry);
        $this->assertSame($delegate->name, $entry['delegate']['name']);
        $this->assertNull($entry['delegate']['sector']);
        $this->assertSame(0, $entry['completed_visits']);
    }
}
