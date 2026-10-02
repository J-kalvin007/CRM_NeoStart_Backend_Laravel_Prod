<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Sanctum\Sanctum;

class Phase3DuoPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;
    protected User $delegate;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'delegue_medical', 'guard_name' => 'web']);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('superviseur');

        $this->delegate = User::factory()->create(['manager_id' => $this->supervisor->id]);
        $this->delegate->assignRole('delegue_medical');
    }

    /**
     * Test 1: Le Superviseur planifie un DUO (seuls délégué, date et heure sont demandés, conflit vérifié).
     */
    public function test_supervisor_plans_duo_with_only_delegate_date_time(): void
    {
        Sanctum::actingAs($this->supervisor);

        $date = now()->addDays(3)->toDateString();

        $response = $this->postJson('/api/v1/activities/duo', [
            'user_id'       => $this->delegate->id,
            'date_activite' => $date,
            'heure_debut'   => '14:00',
            'heure_fin'     => '15:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.statut', 'en_attente');
    }

    public function test_supervisor_duo_proposal_on_existing_visit_does_not_create_duplicate_activity(): void
    {
        Sanctum::actingAs($this->supervisor);

        $existing = Activity::create([
            'user_id' => $this->delegate->id,
            'date_activite' => now()->addDays(2)->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'statut' => 'planifiee',
            'activity_type' => 'visite',
        ]);

        $response = $this->postJson('/api/v1/activities/duo', [
            'activity_id' => $existing->id,
            'user_id' => $this->delegate->id,
            'date_activite' => $existing->date_activite,
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'duo_type' => 'coaching',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseHas('activities', ['id' => $existing->id, 'is_duo' => true, 'duo_user_id' => $this->supervisor->id]);
    }

    /**
     * Test 2: Le Délégué refuse un DUO sans motif -> Le refus est bloqué (422).
     */
    public function test_refusing_duo_without_motif_is_blocked(): void
    {
        $duo = Activity::create([
            'user_id'              => $this->delegate->id,
            'accompanying_user_id' => $this->supervisor->id,
            'date_activite'        => now()->addDays(2)->toDateString(),
            'heure_debut'          => '10:00',
            'heure_fin'            => '11:00',
            'statut'               => 'en_attente',
            'activity_type'        => 'visite',
        ]);

        Sanctum::actingAs($this->delegate);

        $response = $this->postJson("/api/v1/activities/{$duo->id}/refuse-duo", [
            'motif' => '', // Motif vide
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test 3: Le Superviseur consulte un DUO refusé -> Le motif du refus est conservé et visible.
     */
    public function test_supervisor_views_refused_duo_with_motif(): void
    {
        $duo = Activity::create([
            'user_id'              => $this->delegate->id,
            'accompanying_user_id' => $this->supervisor->id,
            'date_activite'        => now()->addDays(2)->toDateString(),
            'heure_debut'          => '10:00',
            'heure_fin'            => '11:00',
            'statut'               => 'en_attente',
            'activity_type'        => 'visite',
        ]);

        Sanctum::actingAs($this->delegate);

        $refuseResponse = $this->postJson("/api/v1/activities/{$duo->id}/refuse-duo", [
            'motif' => 'Prescripteur indisponible à cet horaire',
        ]);
        $refuseResponse->assertStatus(200);

        Sanctum::actingAs($this->supervisor);

        $getResponse = $this->getJson("/api/v1/activities/{$duo->id}");
        $getResponse->assertStatus(200)
            ->assertJsonPath('data.rejection_reason', 'Prescripteur indisponible à cet horaire');
    }

    /**
     * Test 4: Le Délégué accepte un DUO et choisit un type One to One -> Un seul prescripteur sélectionnable.
     */
    public function test_accepting_duo_one_to_one_allows_single_prescripteur(): void
    {
        $duo = Activity::create([
            'user_id'              => $this->delegate->id,
            'accompanying_user_id' => $this->supervisor->id,
            'date_activite'        => now()->addDays(3)->toDateString(),
            'heure_debut'          => '09:00',
            'heure_fin'            => '10:00',
            'statut'               => 'en_attente',
            'activity_type'        => 'visite',
        ]);

        $c1 = Contact::create(['nom' => 'Edem', 'prenom' => 'Dr', 'user_id' => $this->delegate->id]);
        $c2 = Contact::create(['nom' => 'Akofa', 'prenom' => 'Dr', 'user_id' => $this->delegate->id]);

        Sanctum::actingAs($this->delegate);

        // Tentative d'acceptation avec 2 prescripteurs pour One to One -> Refusé 422
        $resMulti = $this->postJson("/api/v1/activities/{$duo->id}/accept-duo", [
            'activity_type' => 'One to One',
            'contacts'      => [$c1->id, $c2->id],
        ]);
        $resMulti->assertStatus(422);

        // Acceptation avec 1 seul prescripteur pour One to One -> Validé 200
        $resSingle = $this->postJson("/api/v1/activities/{$duo->id}/accept-duo", [
            'activity_type' => 'One to One',
            'contact_id'    => $c1->id,
        ]);
        $resSingle->assertStatus(200)
            ->assertJsonPath('data.statut', 'validee');
    }

    /**
     * Test 5: Le Délégué accepte un DUO et choisit un type STAFF -> Plusieurs prescripteurs sélectionnables.
     */
    public function test_accepting_duo_staff_allows_multiple_prescripteurs(): void
    {
        $duo = Activity::create([
            'user_id'              => $this->delegate->id,
            'accompanying_user_id' => $this->supervisor->id,
            'date_activite'        => now()->addDays(4)->toDateString(),
            'heure_debut'          => '14:00',
            'heure_fin'            => '15:30',
            'statut'               => 'en_attente',
            'activity_type'        => 'visite',
        ]);

        $c1 = Contact::create(['nom' => 'Edem', 'prenom' => 'Dr', 'user_id' => $this->delegate->id]);
        $c2 = Contact::create(['nom' => 'Akofa', 'prenom' => 'Dr', 'user_id' => $this->delegate->id]);

        Sanctum::actingAs($this->delegate);

        $response = $this->postJson("/api/v1/activities/{$duo->id}/accept-duo", [
            'activity_type' => 'STAFF',
            'contacts'      => [$c1->id, $c2->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.statut', 'validee');
    }
}
