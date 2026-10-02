<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusNormalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    }

    public function test_user_creation_accepts_active_status_string_and_stores_boolean(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

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
        $admin->assignRole('superadmin');

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

    public function test_supervisor_cannot_create_users(): void
    {
        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        $supervisor = User::factory()->create();
        $supervisor->assignRole('superviseur');
        $targetUser = User::factory()->create();

        $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'Interdit',
                'email' => 'forbidden@example.com',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->putJson("/api/v1/users/{$targetUser->id}", ['name' => 'Modification interdite'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/users/{$targetUser->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $targetUser->id, 'name' => $targetUser->name]);
    }
}
