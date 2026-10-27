<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Product;
use App\Models\SampleMovement;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Sanctum\Sanctum;

class Phase8ReportsSamplesTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;
    protected User $delegate;
    protected Contact $contact;
    protected Product $product;

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

        $this->contact = Contact::create([
            'nom'               => 'Traoré',
            'prenom'            => 'Seydou',
            'user_id'           => $this->delegate->id,
            'statut_validation' => 'valide',
            'statut'            => 'actif',
        ]);

        $this->product = Product::create([
            'nom'   => 'Paracétamol 500mg',
            'code'  => 'PARA500',
            'prix'  => 1000,
        ]);
    }

    /**
     * Test 1: Soumission d'un compte rendu de visite et distribution d'échantillons
     */
    public function test_submit_visit_report_with_sample_distribution(): void
    {
        Sanctum::actingAs($this->delegate);

        $response = $this->postJson('/api/v1/activities', [
            'contact_id'    => $this->contact->id,
            'date_activite' => now()->toDateString(),
            'heure_debut'   => '09:00',
            'heure_fin'     => '10:00',
            'activity_type' => 'visite',
            'statut'        => 'realisee',
            'gps_debut_lat' => 5.3450,
            'gps_debut_lng' => -3.9820,
            'notes'         => 'Compte-rendu détaillé de la visite.',
            'samples'       => [
                [
                    'product_id' => $this->product->id,
                    'quantite'   => 5,
                    'num_lot'    => 'LOT-2026-001',
                ]
            ],
        ]);

        $response->assertStatus(201);
        $activityId = $response->json('data.id');

        $this->assertDatabaseHas('sample_movements', [
            'activity_id'   => $activityId,
            'product_id'    => $this->product->id,
            'movement_type' => 'distribution',
            'quantity'      => 5,
        ]);
    }

    /**
     * Test 2: Validation du compte rendu par le Superviseur
     */
    public function test_supervisor_validates_visit_report(): void
    {
        $activity = Activity::create([
            'user_id'       => $this->delegate->id,
            'contact_id'    => $this->contact->id,
            'date_activite' => now()->toDateString(),
            'heure_debut'   => '10:00',
            'heure_fin'     => '11:00',
            'gps_debut_lat' => 5.3450,
            'gps_debut_lng' => -3.9820,
            'statut'        => 'realisee',
            'report_status' => 'submitted',
            'activity_type' => 'visite',
            'notes'         => 'Compte rendu soumis.',
        ]);

        Sanctum::actingAs($this->supervisor);

        $response = $this->postJson("/api/v1/activities/{$activity->id}/validate", [
            'statut' => 'validee',
        ]);

        $response->assertUnprocessable();
        $this->assertSame('realisee', $activity->fresh()->statut);
        $this->assertSame('submitted', $activity->fresh()->report_status);
    }

    /**
     * Test 3: Rejet du compte rendu par le Superviseur sans motif -> Bloqué avec 422
     */
    public function test_supervisor_rejects_visit_report_without_motif_is_blocked(): void
    {
        $activity = Activity::create([
            'user_id'       => $this->delegate->id,
            'contact_id'    => $this->contact->id,
            'date_activite' => now()->toDateString(),
            'heure_debut'   => '14:00',
            'heure_fin'     => '15:00',
            'gps_debut_lat' => 5.3450,
            'gps_debut_lng' => -3.9820,
            'statut'        => 'realisee',
            'activity_type' => 'visite',
            'notes'         => 'Compte rendu soumis.',
        ]);

        Sanctum::actingAs($this->supervisor);

        $response = $this->postJson("/api/v1/activities/{$activity->id}/validate", [
            'statut'           => 'refusee',
            'rejection_reason' => '',
        ]);

        $response->assertStatus(422);
    }
}
