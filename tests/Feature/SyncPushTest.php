<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class SyncPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_push_endpoint()
    {
        $user = User::factory()->create();

        $payload = [
            'queue' => [
                [
                    'local_id' => 'req_123',
                    'endpoint' => '/api/v1/activities',
                    'method' => 'POST',
                    'payload' => [
                        'activity_type' => 'visite',
                        'date_activite' => '2026-09-18',
                        'statut' => 'soumis_definitif'
                    ]
                ]
            ]
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/sync/push', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'processed_items',
            'errors',
            'timestamp'
        ]);
    }
}
