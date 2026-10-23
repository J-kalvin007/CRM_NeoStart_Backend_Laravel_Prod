<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyCleanupRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_preserves_legacy_rows_and_explicit_manager_links_and_can_run_again(): void
    {
        $manager = User::factory()->create();
        $delegate = User::factory()->create(['manager_id' => null]);
        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('manager_id')->nullable();
            $table->uuid('country_id')->nullable();
        });
        Schema::table('users', fn (Blueprint $table) => $table->uuid('team_id')->nullable());
        $team = (string) Str::uuid();
        DB::table('teams')->insert(['id' => $team, 'name' => 'Équipe historique', 'manager_id' => $manager->id]);
        DB::table('users')->where('id', $delegate->id)->update(['team_id' => $team]);
        $preflight = require database_path('migrations/2026_09_22_085000_preserve_legacy_hierarchy_before_cleanup.php');
        $preflight->up();
        $preflight->up();
        $this->assertSame($manager->id, $delegate->fresh()->manager_id);
        $archive = DB::table('legacy_migration_archive')->where('source_table', 'teams')->where('source_key', $team);
        $this->assertSame(1, $archive->count());
        $this->assertSame('Équipe historique', json_decode($archive->value('payload'), true)['name']);
        $this->assertFalse(Schema::hasColumn('products', 'country_id'), 'Preflight must not recreate retired columns on an already migrated database');
    }
}
