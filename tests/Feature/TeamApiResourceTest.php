<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamApiResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_directory_uses_the_current_manager_relationship(): void
    {
        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        $manager = User::factory()->create();
        $manager->assignRole('superviseur');
        $delegate = User::factory()->create(['manager_id' => $manager->id]);
        $outsider = User::factory()->create();
        $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/users?team=1')->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($delegate->id, $ids);
        $this->assertNotContains($outsider->id, $ids);
        $this->assertNotContains($manager->id, $ids);
        $this->getJson('/api/v1/supervisor/team-planning')->assertOk();
    }

    public function test_admin_can_reassign_a_collaborator_to_another_manager(): void
    {
        Mail::fake();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $manager = User::factory()->create();
        $delegate = User::factory()->create(['manager_id' => $admin->id]);
        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/users/' . $delegate->id, ['manager_id' => $manager->id])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $delegate->id, 'manager_id' => $manager->id]);
    }
}
