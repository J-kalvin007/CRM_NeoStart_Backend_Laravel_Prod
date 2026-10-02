<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Statuts du circuit visite → compte-rendu → validation tels que lus par le web et le mobile. */
class VisitWorkflowStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;
    private User $delegate;
    private Contact $contact;

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
        $this->contact = Contact::create(['nom' => 'Traoré', 'prenom' => 'Seydou', 'user_id' => $this->delegate->id, 'statut_validation' => 'valide', 'statut' => 'actif']);
    }

    private function visit(array $attrs): Activity
    {
        return Activity::create(array_merge([
            'user_id' => $this->delegate->id,
            'contact_id' => $this->contact->id,
            'date_activite' => now()->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'activity_type' => 'visite',
        ], $attrs));
    }

    public function test_report_rejection_keeps_visit_realised_and_reopens_it(): void
    {
        $activity = $this->visit(['statut' => 'realisee', 'report_status' => 'submitted', 'is_locked' => true, 'gps_debut_lat' => 5.3, 'gps_debut_lng' => -3.9]);
        Sanctum::actingAs($this->supervisor);

        $this->postJson("/api/v1/validations/activity_report/{$activity->id}/reject", ['rejection_reason' => 'Produits manquants'])->assertOk();

        $activity->refresh();
        $this->assertSame('realisee', $activity->statut);
        $this->assertSame('rejected', $activity->report_status);
        $this->assertSame('Produits manquants', $activity->rejection_reason);
        $this->assertFalse((bool) $activity->is_locked);
    }

    public function test_report_approval_keeps_visit_realised(): void
    {
        $activity = $this->visit(['statut' => 'realisee', 'report_status' => 'submitted']);
        Sanctum::actingAs($this->supervisor);

        $this->postJson("/api/v1/validations/activity_report/{$activity->id}/approve")->assertOk();

        $activity->refresh();
        $this->assertSame('realisee', $activity->statut);
        $this->assertSame('validated', $activity->report_status);
    }

    public function test_planned_visit_approval_does_not_validate_a_report(): void
    {
        $activity = $this->visit(['statut' => 'en_attente', 'date_activite' => now()->addDay()->toDateString()]);
        Sanctum::actingAs($this->supervisor);

        $this->postJson("/api/v1/validations/visit/{$activity->id}/approve")->assertOk();

        $activity->refresh();
        $this->assertSame('validee', $activity->statut);
        $this->assertNotSame('validated', $activity->report_status);
    }

    public function test_delegate_cannot_validate_own_visit(): void
    {
        $activity = $this->visit(['statut' => 'en_attente', 'date_activite' => now()->addDay()->toDateString()]);
        Sanctum::actingAs($this->delegate);

        $this->putJson("/api/v1/activities/{$activity->id}", ['statut' => 'planifiee'])->assertForbidden();
        $this->assertSame('en_attente', $activity->fresh()->statut);
    }

    public function test_delegate_submits_today_report_and_resubmission_clears_rejection(): void
    {
        $activity = $this->visit(['statut' => 'realisee', 'report_status' => 'rejected', 'rejection_reason' => 'Incomplet', 'gps_debut_lat' => 5.3, 'gps_debut_lng' => -3.9]);
        Sanctum::actingAs($this->delegate);

        $this->putJson("/api/v1/activities/{$activity->id}/report", ['report_content' => 'CR complété', 'report_status' => 'submitted'])
            ->assertOk()
            ->assertJsonPath('report_status', 'submitted');

        $this->assertNull($activity->fresh()->rejection_reason);
    }
}
