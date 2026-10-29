<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Product;
use App\Models\Role;
use App\Models\SampleMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfflineVisitReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $delegate;

    private User $admin;

    private Activity $visit;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $country = Country::create(['name' => 'Togo', 'iso_code' => 'TG']);
        foreach (['superadmin', 'delegue_medical'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->admin = User::factory()->create(['country_id' => $country->id]);
        $this->admin->assignRole('superadmin');
        $this->delegate = User::factory()->create(['country_id' => $country->id]);
        $this->delegate->assignRole('delegue_medical');
        $contact = Contact::create(['user_id' => $this->delegate->id, 'nom' => 'Prescripteur', 'prenom' => 'Réel', 'statut_validation' => 'valide', 'statut' => 'actif']);
        $this->product = Product::create(['nom' => 'Échantillon attribué', 'code' => 'LOT-REAL']);
        $this->visit = Activity::create(['user_id' => $this->delegate->id, 'contact_id' => $contact->id, 'activity_type' => 'visite', 'date_activite' => now()->toDateString(), 'heure_debut' => '09:00', 'statut' => 'en_cours', 'validated_by_id' => $this->admin->id, 'start_time' => now()->subMinutes(20), 'gps_debut_lat' => 6.1, 'gps_debut_lng' => 1.2]);
        SampleMovement::create(['user_id' => $this->delegate->id, 'product_id' => $this->product->id, 'movement_type' => 'attribution', 'quantity' => 10, 'lot_number' => 'LOT-REAL']);
        Sanctum::actingAs($this->delegate);
    }

    private function payload(): array
    {
        return ['statut' => 'realisee', 'report_status' => 'draft', 'report_content' => 'Rapport privé hors ligne', 'heure_fin' => '10:00', 'gps_fin_lat' => 6.1, 'gps_fin_lng' => 1.2, 'samples' => [['product_id' => $this->product->id, 'lot_number' => 'LOT-REAL', 'quantity' => 2]]];
    }

    public function test_offline_report_replay_records_real_stock_once_and_stays_private_until_submission(): void
    {
        $url = '/api/v1/activities/'.$this->visit->id.'/report';
        $this->putJson($url, $this->payload())->assertOk();
        $this->assertDatabaseHas('activities', ['id' => $this->visit->id, 'statut' => 'realisee', 'report_status' => 'draft']);
        $this->assertDatabaseHas('sample_movements', ['activity_id' => $this->visit->id, 'movement_type' => 'distribution', 'quantity' => 2, 'lot_number' => 'LOT-REAL']);
        $this->putJson($url, $this->payload())->assertOk();
        $this->assertSame(1, SampleMovement::where('activity_id', $this->visit->id)->count());
        $this->getJson('/api/v1/my-stock')->assertOk()->assertJsonPath('0.quantity_available', 8);
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/activities?reports_only=1')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/activities/'.$this->visit->id)->assertOk()->assertJsonPath('data.report_content', null)->assertDontSee('Rapport privé hors ligne');
        Sanctum::actingAs($this->delegate);
        $this->putJson($url, ['report_status' => 'submitted', 'report_content' => 'Rapport transmis'])->assertOk();
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/activities?reports_only=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_insufficient_real_stock_rolls_back_the_checkout_and_report(): void
    {
        $before = $this->visit->fresh();
        $payload = $this->payload();
        $payload['samples'][0]['quantity'] = 11;
        $this->putJson('/api/v1/activities/'.$this->visit->id.'/report', $payload)->assertUnprocessable();
        $this->assertDatabaseHas('activities', ['id' => $this->visit->id, 'statut' => 'en_cours', 'report_status' => $before->report_status, 'report_content' => $before->getRawOriginal('report_content')]);
        $this->assertSame(0, SampleMovement::where('activity_id', $this->visit->id)->count());
    }

    public function test_closed_visit_does_not_silently_accept_a_different_sample_remittance(): void
    {
        $url = '/api/v1/activities/'.$this->visit->id.'/report';
        $this->putJson($url, $this->payload())->assertOk();
        $payload = $this->payload();
        $payload['samples'][0]['quantity'] = 3;
        $this->putJson($url, $payload)->assertUnprocessable();
        $this->getJson('/api/v1/my-stock')->assertOk()->assertJsonPath('0.quantity_available', 8);
    }

    public function test_missing_objection_product_or_reaction_does_not_invent_report_data(): void
    {
        $url = '/api/v1/activities/'.$this->visit->id.'/report';
        $payload = $this->payload();
        $payload['objections'] = [['title' => 'Objection relevée', 'physician_reaction' => 'refus']];
        $this->putJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseHas('activities', ['id' => $this->visit->id, 'statut' => 'en_cours']);
        $this->assertDatabaseCount('objections', 0);
        $payload['objections'] = [['product_id' => $this->product->id, 'title' => 'Objection relevée']];
        $this->putJson($url, $payload)->assertUnprocessable();
        $this->assertSame(0, SampleMovement::where('activity_id', $this->visit->id)->count());
        $payload['objections'][0]['physician_reaction'] = 'refus';
        $this->putJson($url, $payload)->assertOk();
        $this->assertDatabaseHas('objections', ['product_id' => $this->product->id, 'title' => 'Objection relevée', 'recommended_argument' => '']);
        $this->assertDatabaseHas('activity_objections', ['activity_id' => $this->visit->id, 'physician_reaction' => 'refus']);
    }

    public function test_a_report_cannot_publish_an_unexecuted_visit_or_rewind_its_status(): void
    {
        $this->visit->update(['statut' => 'brouillon']);
        $url = '/api/v1/activities/'.$this->visit->id.'/report';
        $this->putJson($url, ['report_status' => 'submitted', 'report_content' => 'Tentative avant exécution'])->assertUnprocessable();
        $this->putJson($url, ['report_status' => 'draft', 'statut' => 'planifiee'])->assertUnprocessable();
        $this->putJson($url, ['report_status' => 'draft', 'statut' => 'realisee'])->assertUnprocessable();
        $this->assertDatabaseHas('activities', ['id' => $this->visit->id, 'statut' => 'brouillon']);
        $this->assertSame(0, SampleMovement::where('activity_id', $this->visit->id)->count());
    }
}
