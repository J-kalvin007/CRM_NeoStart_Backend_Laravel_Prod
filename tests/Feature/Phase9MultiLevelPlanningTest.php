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

class Phase9MultiLevelPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $salesManager;
    protected User $supervisor;
    protected User $delegate;
    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'sales_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'delegue_medical', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('superadmin');

        $this->salesManager = User::factory()->create();
        $this->salesManager->assignRole('sales_manager');

        // Le Sales Manager ne voit que son équipe : superviseur rattaché à lui
        $this->supervisor = User::factory()->create(['manager_id' => $this->salesManager->id]);
        $this->supervisor->assignRole('superviseur');

        $this->delegate = User::factory()->create(['manager_id' => $this->supervisor->id]);
        $this->delegate->assignRole('delegue_medical');

        $this->contact = Contact::create([
            'nom'               => 'Konan',
            'prenom'            => 'Yves',
            'user_id'           => $this->delegate->id,
            'statut_validation' => 'valide',
            'statut'            => 'actif',
        ]);
    }

    /**
     * Test 1: Le Délégué consulte son propre planning
     */
    public function test_delegate_views_own_planning(): void
    {
        $visit = Activity::create([
            'user_id'       => $this->delegate->id,
            'contact_id'    => $this->contact->id,
            'date_activite' => now()->addDay()->toDateString(),
            'heure_debut'   => '09:00',
            'heure_fin'     => '10:00',
            'statut'        => 'planifiee',
            'activity_type' => 'visite',
        ]);

        Sanctum::actingAs($this->delegate);

        $response = $this->getJson('/api/v1/activities');

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $visit->id]);
    }

    /**
     * Test 2: Le Superviseur consulte le planning de son délégué
     */
    public function test_supervisor_views_subordinate_delegate_planning(): void
    {
        $visit = Activity::create([
            'user_id'       => $this->delegate->id,
            'contact_id'    => $this->contact->id,
            'date_activite' => now()->addDays(2)->toDateString(),
            'heure_debut'   => '10:00',
            'heure_fin'     => '11:00',
            'statut'        => 'validee',
            'activity_type' => 'visite',
        ]);

        Sanctum::actingAs($this->supervisor);

        $response = $this->getJson("/api/v1/activities?user_id={$this->delegate->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $visit->id]);
    }

    /**
     * Test 3: Le Sales Manager consulte les visites du périmètre
     */
    public function test_sales_manager_views_all_perimeter_plannings(): void
    {
        $visit = Activity::create([
            'user_id'       => $this->delegate->id,
            'contact_id'    => $this->contact->id,
            'date_activite' => now()->addDays(3)->toDateString(),
            'heure_debut'   => '14:00',
            'heure_fin'     => '15:00',
            'statut'        => 'validee',
            'activity_type' => 'visite',
        ]);

        Sanctum::actingAs($this->salesManager);

        $response = $this->getJson('/api/v1/activities');

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $visit->id]);
    }

    /**
     * Test 4: Le Super Admin ne peut pas planifier une visite (403)
     */
    public function test_superadmin_cannot_plan_visit(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson('/api/v1/activities', [
            'user_id'       => $this->delegate->id,
            'date_activite' => now()->addDays(4)->toDateString(),
            'heure_debut'   => '15:00',
            'heure_fin'     => '16:00',
            'activity_type' => 'visite',
        ]);

        $response->assertStatus(403);
    }
}
