<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creation_accepts_active_status_string_and_stores_boolean(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'Alice Test',
            'email' => 'alice@example.com',
            'status' => 'active',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'status' => true,
        ]);
    }

    public function test_user_creation_accepts_inactive_status_string_and_stores_boolean(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'Bob Test',
            'email' => 'bob@example.com',
            'status' => 'inactive',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'bob@example.com',
            'status' => false,
        ]);
    }
}
