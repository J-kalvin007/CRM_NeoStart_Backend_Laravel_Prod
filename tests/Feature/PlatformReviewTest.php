<?php

namespace Tests\Feature;

use App\Models\{Activity, Country, Role, Setting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Cache, Mail, Storage};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_progress_is_personal_and_category_filters_use_real_columns(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $training = \App\Models\Training::create(['title' => 'Formation produits', 'category' => 'Produits', 'status' => 'active']);
        Sanctum::actingAs($first);
        $this->patchJson('/api/v1/trainings/'.$training->id.'/progress', ['progress' => 100])->assertOk()->assertJsonPath('progress', 100)->assertJsonPath('status', 'TERMINE');
        Sanctum::actingAs($second);
        $this->getJson('/api/v1/trainings/'.$training->id)->assertOk()->assertJsonPath('progress', 0)->assertJsonPath('status', 'A_FAIRE');
        $this->getJson('/api/v1/trainings?category=Produits&search=Formation')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.progress', 0);
        Sanctum::actingAs($first);
        $this->getJson('/api/v1/trainings/'.$training->id)->assertOk()->assertJsonPath('progress', 100);
    }

    public function test_disabled_accounts_cannot_login_or_use_an_existing_session(): void
    {
        $user = User::factory()->create(['status' => false]);
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])->assertForbidden();
        Cache::put('login_otp_'.$user->id, '123456', 600);
        $this->postJson('/api/v1/verify-otp', ['user_id' => $user->id, 'otp_code' => '123456'])->assertForbidden();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/me')->assertForbidden();
        $this->getJson('/api/v1/activities')->assertForbidden();
    }

    public function test_otp_resend_requires_an_existing_login_challenge(): void
    {
        Mail::fake();
        $user = User::factory()->create(['otp_enabled' => true]);
        $this->postJson('/api/v1/resend-otp', ['user_id' => $user->id])->assertUnprocessable();
        Mail::assertNothingSent();
    }

    public function test_password_token_is_verified_and_reset_revokes_old_sessions(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $user->createToken('old-session');
        Cache::put('password_reset_'.$user->email, str_repeat('A', 60), 600);
        $payload = ['email' => $user->email, 'token' => str_repeat('A', 60)];
        $this->postJson('/api/v1/password/verify', array_merge($payload, ['token' => 'wrong']))->assertUnprocessable();
        $this->postJson('/api/v1/password/verify', $payload)->assertOk();
        $this->postJson('/api/v1/password/reset', array_merge($payload, ['password' => 'new-password', 'password_confirmation' => 'new-password']))->assertOk();
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/password/verify', $payload)->assertUnprocessable();
    }

    public function test_public_settings_exclude_private_configuration_and_upload_rejects_executable_content(): void
    {
        Setting::create(['key' => 'app_name', 'value' => 'CRM']);
        Setting::create(['key' => 'smtp_password', 'value' => 'PRIVATE-CONFIG']);
        $this->getJson('/api/v1/settings')->assertOk()->assertJsonPath('app_name', 'CRM')->assertJsonMissingPath('smtp_password');
        Sanctum::actingAs(User::factory()->create());
        Storage::fake('public');
        $this->post('/api/v1/media/upload', ['file' => UploadedFile::fake()->createWithContent('exploit.php', '<?php echo 1;')], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_sync_pull_works_with_current_leave_model_and_keeps_drafts_private(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin->assignRole('superadmin');
        $other = User::factory()->create();
        $draft = Activity::create(['user_id' => $other->id, 'activity_type' => 'permission', 'statut' => 'brouillon', 'date_activite' => now(), 'notes' => 'PRIVATE-LEAVE']);
        $visible = Activity::create(['user_id' => $other->id, 'activity_type' => 'conge', 'statut' => 'validee', 'date_activite' => now()]);
        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/sync/pull')->assertOk()->assertDontSee('PRIVATE-LEAVE');
        $this->assertNotContains($draft->id, array_column($response->json('data.activities'), 'id'));
        $this->assertSame([$visible->id], array_column($response->json('data.leaves'), 'id'));
    }

    public function test_sync_replays_absolute_api_paths_and_rejects_recursive_or_external_destinations(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $payload = ['title' => 'Tâche hors ligne', 'type' => 'other', 'status' => 'planned', 'start_time' => now()->addDay()->toDateTimeString()];
        $response = $this->postJson('/api/v1/sync/push', ['queue' => [
            ['endpoint' => '/api/v1/tasks', 'method' => 'POST', 'payload' => $payload],
            ['endpoint' => 'sync/push', 'method' => 'POST', 'payload' => []],
            ['endpoint' => 'https://example.com/tasks', 'method' => 'POST', 'payload' => []],
        ]])->assertOk()->assertJsonPath('processed_items', 1)->assertJsonCount(2, 'errors');
        $this->assertDatabaseHas('tasks', ['title' => 'Tâche hors ligne', 'user_id' => $user->id]);
        $this->postJson('/api/v1/sync/push', ['queue' => 'invalid'])->assertUnprocessable();
    }
}
