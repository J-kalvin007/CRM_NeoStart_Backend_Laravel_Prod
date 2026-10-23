<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HierarchyConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_hierarchy_uses_manager_assignments_without_retired_configs(): void
    {
        $this->assertFalse(Schema::hasTable('organization_configs'));
        $manager = User::factory()->create();
        $supervisor = User::factory()->create(['manager_id' => $manager->id]);
        $delegate = User::factory()->create(['manager_id' => $supervisor->id]);
        $other = User::factory()->create();
        $this->assertContains($delegate->id, $manager->teamMemberIds());
        $this->assertNotContains($other->id, $manager->teamMemberIds());
        $this->assertTrue($delegate->manager->is($supervisor));
    }
}
