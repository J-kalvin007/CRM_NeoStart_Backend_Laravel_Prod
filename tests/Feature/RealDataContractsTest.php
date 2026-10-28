<?php
namespace Tests\Feature;
use App\Models\{User, Objectif};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class RealDataContractsTest extends TestCase
{
    use RefreshDatabase;
    public function test_debug_mode_does_not_accept_an_invented_otp(): void
    {
        config(['app.debug'=>true]);
        $user=User::factory()->create();
        foreach (['123456','000000'] as $code) $this->postJson('/api/v1/verify-otp',['user_id'=>$user->id,'otp_code'=>$code])->assertUnauthorized();
        Cache::put('login_otp_'.$user->id,'654321',now()->addMinutes(10));
        $this->postJson('/api/v1/verify-otp',['user_id'=>$user->id,'otp_code'=>'654321'])->assertOk()->assertJsonStructure(['token']);
    }
    public function test_profile_updates_persist_only_the_authenticated_users_editable_fields(): void
    {
        $owner=User::factory()->create();$other=User::factory()->create();
        $payload=['name'=>'Nom réel','email'=>'real@example.test','phone'=>'1234','manager_id'=>$other->id,'id'=>$other->id];
        $this->patchJson('/api/v1/me',$payload)->assertUnauthorized();
        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/me',$payload)->assertOk()->assertJsonPath('id',$owner->id);
        $this->assertDatabaseHas('users',['id'=>$owner->id,'name'=>'Nom réel','phone'=>'1234','manager_id'=>null]);
        $this->assertSame($other->name,$other->fresh()->name);
    }
    public function test_team_metrics_use_real_objectives_without_a_synthetic_quota(): void
    {
        $manager=User::factory()->create();$delegate=User::factory()->create(['manager_id'=>$manager->id]);Sanctum::actingAs($manager);
        $response=$this->getJson('/api/v1/analytics/team-performance')->assertOk();
        $entry=collect($response->json('data'))->firstWhere('id',$delegate->id);
        $this->assertSame(0,$entry['target_visits']);$this->assertNull($entry['objective_rate']);
        Objectif::create(['user_id'=>$delegate->id,'date_debut'=>now()->startOfMonth()->toDateString(),'date_fin'=>now()->endOfMonth()->toDateString(),'valeur_cible'=>10,'valeur_realisee'=>5,'statut'=>'actif']);
        $entry=collect($this->getJson('/api/v1/analytics/team-performance')->assertOk()->json('data'))->firstWhere('id',$delegate->id);
        $this->assertEquals(50,$entry['objective_rate']);
    }
    public function test_orders_require_real_amounts_and_do_not_invent_a_discount_or_quantity(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/orders',['clientNom'=>'Client réel'])->assertUnprocessable();
        $response=$this->postJson('/api/v1/orders',['clientNom'=>'Client réel','montantTotal'=>0])->assertCreated()->assertJsonPath('montantTotal',0)->assertJsonPath('produitsCount',null)->assertJsonPath('remiseAppliquee',null);
        $this->assertDatabaseHas('orders',['id'=>$response->json('id'),'total_amount'=>0]);
    }
    public function test_default_seeding_does_not_create_demo_business_records_or_users(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        foreach (['users','accounts','contacts','activities','products','orders','expenses'] as $table) $this->assertDatabaseCount($table,0);
    }
}
