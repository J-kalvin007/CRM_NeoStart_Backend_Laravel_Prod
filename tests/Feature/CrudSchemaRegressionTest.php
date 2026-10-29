<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\Country;
use App\Models\FieldPost;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrudSchemaRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->country = Country::create(['name' => 'Togo', 'iso_code' => 'TG']);
        $this->admin = User::factory()->create(['country_id' => $this->country->id]);
        $this->admin->assignRole('superadmin');
        Sanctum::actingAs($this->admin);
    }

    private function campaignBody(): array
    {
        return ['country_id' => $this->country->id, 'nom' => 'Campagne de recette', 'date_debut' => '2026-11-01',
            'date_fin' => '2026-11-30', 'budget' => 25000, 'message' => 'Objectif terrain',
            'meta' => ['type_campagne' => 'Éducation'], 'statut' => 'brouillon'];
    }

    public function test_campaign_form_payload_create_read_edit_status_and_delete(): void
    {
        $id = $this->withHeader('X-Country-Id', $this->country->id)->postJson('/api/v1/campaigns', $this->campaignBody())
            ->assertCreated()->assertJsonPath('country_id', $this->country->id)->assertJsonPath('meta.type_campagne', 'Éducation')->json('id');
        $this->getJson('/api/v1/campaigns/'.$id)->assertOk()->assertJsonPath('nom', 'Campagne de recette');
        $this->putJson('/api/v1/campaigns/'.$id, ['message' => 'Modification conservée', 'date_fin' => '2026-12-10'])
            ->assertOk()->assertJsonPath('message', 'Modification conservée');
        foreach (['validation', 'active', 'suspendue', 'active', 'terminee'] as $status) {
            $this->patchJson('/api/v1/campaigns/'.$id.'/status', ['statut' => $status])->assertOk()->assertJsonPath('statut', $status);
        }
        $this->patchJson('/api/v1/campaigns/'.$id.'/status', ['statut' => 'brouillon'])->assertUnprocessable()->assertJsonValidationErrors('statut');
        $this->deleteJson('/api/v1/campaigns/'.$id)->assertNoContent();
        $this->assertSoftDeleted('campaigns', ['id' => $id]);
        $this->getJson('/api/v1/campaigns/'.$id)->assertNotFound();
    }

    public function test_campaign_validation_is_actionable_and_partial_edits_cannot_invert_dates(): void
    {
        $this->postJson('/api/v1/campaigns', array_replace($this->campaignBody(), ['country_id' => 'group']))->assertUnprocessable()->assertJsonValidationErrors('country_id');
        $this->postJson('/api/v1/campaigns', array_replace($this->campaignBody(), ['budget' => -1]))->assertUnprocessable()->assertJsonValidationErrors('budget');
        $id = $this->postJson('/api/v1/campaigns', $this->campaignBody())->assertCreated()->json('id');
        $this->putJson('/api/v1/campaigns/'.$id, ['date_fin' => '2026-10-01'])->assertUnprocessable()->assertJsonValidationErrors('date_fin');
        $this->putJson('/api/v1/campaigns/'.$id, ['date_debut' => '2027-01-01'])->assertUnprocessable()->assertJsonValidationErrors('date_fin');
        $this->assertSame('2026-11-01', Campaign::findOrFail($id)->date_debut->toDateString());
    }

    public function test_campaigns_cannot_be_created_or_changed_in_another_selected_country(): void
    {
        $other = Country::create(['name' => 'Bénin', 'iso_code' => 'BJ']);
        $this->withHeader('X-Country-Id', $other->id)->postJson('/api/v1/campaigns', $this->campaignBody())->assertUnprocessable()->assertJsonValidationErrors('country_id');
        $this->assertDatabaseCount('campaigns', 0);
        $id = $this->withHeader('X-Country-Id', $this->country->id)->postJson('/api/v1/campaigns', $this->campaignBody())->assertCreated()->json('id');
        $this->withHeader('X-Country-Id', $other->id)->putJson('/api/v1/campaigns/'.$id, ['nom' => 'Fuite'])->assertNotFound();
        $this->getJson('/api/v1/campaigns')->assertExactJson([]);
        Sanctum::actingAs(User::factory()->create(['country_id' => $other->id]));
        $this->postJson('/api/v1/campaigns', array_replace($this->campaignBody(), ['country_id' => $other->id]))->assertForbidden();
    }

    public function test_supervisor_manages_own_country_campaigns_without_cross_country_access(): void
    {
        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        $supervisor = User::factory()->create(['country_id' => $this->country->id]);
        $supervisor->assignRole('superviseur');
        Sanctum::actingAs($supervisor);
        $id = $this->postJson('/api/v1/campaigns', $this->campaignBody())->assertCreated()->json('id');
        $this->putJson('/api/v1/campaigns/'.$id, ['nom' => 'Campagne de l’équipe'])->assertOk();
        $other = Country::create(['name' => 'Bénin', 'iso_code' => 'BJ']);
        $foreign = Campaign::withoutGlobalScopes()->create(array_replace($this->campaignBody(), ['country_id' => $other->id]));
        $this->withHeader('X-Country-Id', $other->id)->postJson('/api/v1/campaigns', array_replace($this->campaignBody(), ['country_id' => $other->id]))->assertUnprocessable()->assertJsonValidationErrors('country_id');
        $this->getJson('/api/v1/campaigns')->assertExactJson([]);
        $this->putJson('/api/v1/campaigns/'.$foreign->id, ['nom' => 'Accès refusé'])->assertNotFound();
    }

    public function test_merged_compatibility_keeps_canonical_statuses_and_requires_country_in_group_mode(): void
    {
        $body = $this->campaignBody();
        unset($body['country_id']);
        $this->withHeader('X-Country-Id', 'group')->postJson('/api/v1/campaigns', $body)
            ->assertUnprocessable()->assertJsonValidationErrors('country_id');
        $this->withHeader('X-Country-Id', 'unknown-country')->postJson('/api/v1/campaigns', $body)
            ->assertUnprocessable()->assertJsonValidationErrors('country_id');
        $body['statut'] = 'creation';
        $id = $this->withHeader('X-Country-Id', $this->country->id)->postJson('/api/v1/campaigns', $body)
            ->assertCreated()->assertJsonPath('statut', 'brouillon')->assertJsonPath('country_id', $this->country->id)->json('id');
        $this->patchJson('/api/v1/campaigns/'.$id.'/status', ['statut' => 'deploiement'])
            ->assertUnprocessable()->assertJsonValidationErrors('statut');
        $this->patchJson('/api/v1/campaigns/'.$id.'/status', ['statut' => 'validation'])->assertOk();
        $this->patchJson('/api/v1/campaigns/'.$id.'/status', ['statut' => 'deploiement'])->assertOk()->assertJsonPath('statut', 'active');
        $this->putJson('/api/v1/campaigns/'.$id, ['statut' => 'cloture'])->assertOk()->assertJsonPath('statut', 'terminee');
        $this->patchJson('/api/v1/campaigns/'.$id.'/status', ['statut' => 'creation'])
            ->assertUnprocessable()->assertJsonValidationErrors('statut');
    }

    public function test_missing_field_posts_returns_explicit_503_without_sql_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        Schema::rename('field_posts', 'field_posts_unavailable');
        try {
            foreach (['/api/v1/analytics/executive', '/api/v1/reports/overview?kind=executive', '/api/v1/delegates'] as $url) {
                $response = $this->getJson($url)->assertStatus(503)->assertJsonPath('code', 'DATABASE_SCHEMA_NOT_READY');
                $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
                $this->assertStringNotContainsString('select ', $response->getContent());
                $response->assertJsonStructure(['message', 'code']);
            }
        } finally {
            Schema::rename('field_posts_unavailable', 'field_posts');
        }
    }

    public function test_missing_conversation_country_column_is_actionable_without_sql(): void
    {
        DB::statement('ALTER TABLE conversations RENAME COLUMN country_id TO country_id_unavailable');
        try {
            $this->withHeader('X-Country-Id', $this->country->id)->getJson('/api/v1/conversations')->assertStatus(503)
                ->assertExactJson(['message' => 'La base de données doit être mise à jour. Faites exécuter php artisan migrate --force dans CRM-backend, puis réessayez.', 'code' => 'DATABASE_SCHEMA_NOT_READY']);
        } finally {
            DB::statement('ALTER TABLE conversations RENAME COLUMN country_id_unavailable TO country_id');
        }
    }

    public function test_partial_migration_recovery_preserves_existing_users_posts_and_conversations(): void
    {
        $conversation = Conversation::create(['title' => 'Conversation conservée', 'type' => 'direct', 'created_by' => $this->admin->id]);
        $conversation->users()->attach($this->admin->id);
        $post = FieldPost::create(['country_id' => $this->country->id, 'name' => 'Poste conservé']);
        Schema::drop('message_reads');
        Schema::drop('message_attachments');
        foreach (['100000_create_compliance_findings_table', '110000_extend_private_conversations', '120000_create_field_posts', '130000_create_leave_workflow'] as $name) {
            $migration = require database_path('migrations/2026_10_29_'.$name.'.php');
            $migration->up();
            $migration->up();
        }
        $this->assertTrue(Schema::hasColumn('users', 'field_post_id'));
        $this->assertTrue(Schema::hasTable('message_reads'));
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
        $this->assertDatabaseHas('field_posts', ['id' => $post->id, 'name' => 'Poste conservé']);
        $this->assertDatabaseHas('conversations', ['id' => $conversation->id, 'title' => 'Conversation conservée']);
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.id', $conversation->id);
        $this->getJson('/api/v1/analytics/executive')->assertOk();
    }

    public function test_campaign_migration_converts_legacy_statuses_and_keeps_records(): void
    {
        foreach (['creation' => 'brouillon', 'validation' => 'validation', 'deploiement' => 'active', 'execution' => 'active', 'suivi' => 'active', 'analyse' => 'active', 'cloture' => 'terminee'] as $old => $new) {
            Campaign::create(array_replace($this->campaignBody(), ['nom' => $old, 'statut' => $old]));
        }
        $migration = require database_path('migrations/2026_10_29_140000_align_campaign_statuses.php');
        $migration->up();
        $migration->up();
        foreach (['creation' => 'brouillon', 'validation' => 'validation', 'deploiement' => 'active', 'execution' => 'active', 'suivi' => 'active', 'analyse' => 'active', 'cloture' => 'terminee'] as $old => $new) {
            $this->assertDatabaseHas('campaigns', ['nom' => $old, 'statut' => $new]);
        }
    }

    public function test_every_registered_controller_action_exists(): void
    {
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_starts_with($action, 'App\\Http\\Controllers\\')) {
                continue;
            }
            [$class, $method] = str_contains($action, '@') ? explode('@', $action, 2) : [$action, '__invoke'];
            $this->assertTrue(method_exists($class, $method), $route->uri().' -> '.$action);
            $checked++;
        }
        $this->assertGreaterThan(100, $checked);
    }
}
