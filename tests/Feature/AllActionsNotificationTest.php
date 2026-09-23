<?php

namespace Tests\Feature;

use App\Mail\SkynityNotificationMail;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Subsidiary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AllActionsNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;
    protected User $delegate;
    protected Country $country;
    protected Subsidiary $subsidiary;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->country = Country::create([
            'name' => 'Bénin',
            'iso_code' => 'BJ',
            'currency' => 'XOF',
        ]);

        $this->subsidiary = Subsidiary::create([
            'name' => 'Filiale Bénin',
            'code' => 'SUB-BJ',
            'country_id' => $this->country->id,
            'status' => true,
        ]);

        $this->supervisor = User::factory()->create([
            'name' => 'Superviseur Test',
            'email' => 'supervisor_all@skynity.test',
            'subsidiary_id' => $this->subsidiary->id,
        ]);

        $this->delegate = User::factory()->create([
            'name' => 'Délégué Test',
            'email' => 'delegate_all@skynity.test',
            'manager_id' => $this->supervisor->id,
            'subsidiary_id' => $this->subsidiary->id,
        ]);
    }

    public function test_tour_plan_submission_triggers_notifications()
    {
        $response = $this->actingAs($this->delegate, 'sanctum')
            ->postJson('/api/v1/tour-plans', [
                'nom' => 'Plan de tournée S38',
                'date_debut' => '2026-09-22',
                'date_fin' => '2026-09-28',
                'statut' => 'soumis',
            ]);

        $response->assertStatus(201);

        // Assert DB Notification created for supervisor
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->supervisor->id,
        ]);

        // Assert Email sent
        Mail::assertSent(SkynityNotificationMail::class, function ($mail) {
            return $mail->hasTo('supervisor_all@skynity.test');
        });
    }

    public function test_expense_submission_and_approval_triggers_notifications()
    {
        $expense = Expense::create([
            'user_id' => $this->delegate->id,
            'subsidiary_id' => $this->subsidiary->id,
            'title' => 'Déjeuner Pro',
            'type' => 'meal',
            'amount' => 25000,
            'currency' => 'XOF',
            'expense_date' => '2026-09-22',
            'status' => 'draft',
        ]);

        // 1. Submit expense by delegate
        $resSubmit = $this->actingAs($this->delegate, 'sanctum')
            ->postJson("/api/v1/expenses/{$expense->id}/submit");
        $resSubmit->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->supervisor->id,
        ]);

        // 2. Approve expense by supervisor
        $resApprove = $this->actingAs($this->supervisor, 'sanctum')
            ->patchJson("/api/v1/expenses/{$expense->id}/status", [
                'status' => 'approved',
            ]);
        $resApprove->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->delegate->id,
        ]);
    }

    public function test_delegate_request_submission_triggers_notifications()
    {
        $response = $this->actingAs($this->delegate, 'sanctum')
            ->postJson('/api/v1/requests', [
                'request_type' => 'echantillons_supplementaires',
                'reason' => 'Besoin d\'échantillons pour campagne pédiatriques',
                'priority' => 'high',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->supervisor->id,
        ]);
        Mail::assertSent(SkynityNotificationMail::class);
    }

    public function test_coaching_session_creation_triggers_notifications()
    {
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson('/api/v1/coaching-sessions', [
                'delegate_id' => $this->delegate->id,
                'date_duo' => '2026-09-25',
                'zone' => 'Cotonou Littoral',
                'motif' => 'Évaluation technique de vente',
                'objectifs_prevus' => ['Présentation produit A', 'Gestion des objections'],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->delegate->id,
        ]);
        Mail::assertSent(SkynityNotificationMail::class, function ($mail) {
            return $mail->hasTo('delegate_all@skynity.test');
        });
    }

    public function test_pharmacovigilance_report_triggers_notifications()
    {
        $product = Product::create([
            'nom' => 'SkynityCure 500mg',
            'subsidiary_id' => $this->subsidiary->id,
        ]);

        $contact = Contact::create([
            'nom' => 'Lawson',
            'prenom' => 'Jean',
            'user_id' => $this->delegate->id,
            'subsidiary_id' => $this->subsidiary->id,
        ]);

        $response = $this->actingAs($this->delegate, 'sanctum')
            ->postJson('/api/v1/pharmacovigilance-reports', [
                'contact_id' => $contact->id,
                'product_id' => $product->id,
                'patient_initials' => 'A.K.',
                'adverse_event_description' => 'Réaction allergique cutanée sévère.',
                'severity' => 'grave',
                'report_date' => '2026-09-22',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->supervisor->id,
        ]);
        Mail::assertSent(SkynityNotificationMail::class);
    }
}
