<?php

namespace Tests\Feature;

use App\Mail\SkynityNotificationMail;
use App\Models\Product;
use App\Models\Role;
use App\Models\SampleMovement;
use App\Models\SampleRequest;
use App\Models\SampleStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SampleRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;
    protected User $delegate;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'delegue_medical', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'sales_manager', 'guard_name' => 'web']);

        $this->supervisor = User::factory()->create(['email' => 'supervisor@example.com']);
        $this->supervisor->assignRole('superviseur');
        $this->delegate = User::factory()->create([
            'email' => 'delegate@example.com',
            'manager_id' => $this->supervisor->id,
        ]);
        $this->delegate->assignRole('delegue_medical');
        $this->product = Product::create(['nom' => 'Produit test', 'code' => 'SAMPLE-01', 'prix' => 100]);
    }

    public function test_exhausted_entrusted_stock_remains_visible_without_adding_catalogue_products(): void
    {
        $this->product->update(['statut' => 'inactif']);
        Product::create(['nom' => 'Catalogue sans dotation', 'code' => 'NO-ALLOCATION']);
        foreach (['reception', 'distribution'] as $type) {
            SampleMovement::create(['user_id' => $this->delegate->id, 'product_id' => $this->product->id,
                'movement_type' => $type, 'quantity' => 8, 'stock_apres' => $type === 'reception' ? 8 : 0]);
        }
        Sanctum::actingAs($this->delegate);
        $this->getJson('/api/v1/my-stock')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.quantity_available', 0)->assertJsonPath('0.quantity_received', 8)
            ->assertJsonPath('0.quantity_distributed', 8)->assertJsonPath('0.lots', []);
    }

    public function test_delegate_submits_sample_request_to_direct_supervisor(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->delegate);

        $response = $this->postJson('/api/v1/sample-requests', [
            'product_id' => $this->product->id,
            'quantity' => 12,
            'notes' => 'Préparation de la campagne terrain',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('quantity', 12)
            ->assertJsonPath('notes', 'Préparation de la campagne terrain');
        $this->assertDatabaseHas('sample_requests', [
            'user_id' => $this->delegate->id,
            'product_id' => $this->product->id,
            'quantity' => 12,
            'notes' => 'Préparation de la campagne terrain',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->supervisor->id]);
        Mail::assertSent(SkynityNotificationMail::class, fn ($mail) => $mail->hasTo($this->supervisor->email));
    }

    public function test_direct_supervisor_approval_records_delivered_quantity_and_increments_delegate_stock(): void
    {
        Mail::fake();
        $sampleRequest = SampleRequest::create([
            'user_id' => $this->delegate->id,
            'product_id' => $this->product->id,
            'quantity' => 12,
            'status' => 'pending',
            'notes' => 'Réassort',
        ]);

        Sanctum::actingAs($this->supervisor);
        $response = $this->postJson("/api/v1/sample-requests/{$sampleRequest->id}/approve", [
            'quantity_delivered' => 8,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('approved_quantity', 8);
        $this->assertDatabaseHas('sample_requests', [
            'id' => $sampleRequest->id,
            'status' => 'approved',
            'approved_by' => $this->supervisor->id,
            'approved_quantity' => 8,
        ]);
        $this->assertDatabaseHas('sample_stocks', [
            'user_id' => $this->delegate->id,
            'product_id' => $this->product->id,
            'quantity' => 8,
        ]);
        $this->assertDatabaseHas('sample_movements', [
            'user_id' => $this->delegate->id,
            'product_id' => $this->product->id,
            'movement_type' => 'reception',
            'quantity' => 8,
            'stock_apres' => 8,
        ]);
        // Le même solde remis depuis le web est utilisé par l'écran stock mobile.
        Sanctum::actingAs($this->delegate);
        $this->getJson('/api/v1/my-stock')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.product_id', $this->product->id)
            ->assertJsonPath('0.quantity_received', 8)
            ->assertJsonPath('0.quantity_available', 8)
            ->assertJsonPath('0.quantity_distributed', 0);
        Sanctum::actingAs($this->supervisor);
        $this->getJson('/api/v1/my-stock')->assertOk()->assertExactJson([]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->delegate->id]);
        Mail::assertSent(SkynityNotificationMail::class, fn ($mail) => $mail->hasTo($this->delegate->email));
    }

    public function test_rejection_requires_a_reason_and_notifies_delegate(): void
    {
        Mail::fake();
        $sampleRequest = SampleRequest::create([
            'user_id' => $this->delegate->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'status' => 'pending',
            'notes' => 'Réassort',
        ]);

        Sanctum::actingAs($this->supervisor);
        $this->postJson("/api/v1/sample-requests/{$sampleRequest->id}/reject", [])
            ->assertUnprocessable();

        $response = $this->postJson("/api/v1/sample-requests/{$sampleRequest->id}/reject", [
            'rejection_reason' => 'Stock central indisponible',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('rejection_reason', 'Stock central indisponible');
        $this->assertDatabaseHas('sample_requests', [
            'id' => $sampleRequest->id,
            'status' => 'rejected',
            'rejection_reason' => 'Stock central indisponible',
        ]);
        $this->assertDatabaseMissing('sample_stocks', [
            'user_id' => $this->delegate->id,
            'product_id' => $this->product->id,
        ]);
        Mail::assertSent(SkynityNotificationMail::class, fn ($mail) => $mail->hasTo($this->delegate->email));
    }

    public function test_indirect_manager_cannot_approve_sample_request(): void
    {
        $higherManager = User::factory()->create(['manager_id' => $this->supervisor->id]);
        $higherManager->assignRole('sales_manager');
        $sampleRequest = SampleRequest::create([
            'user_id' => $this->delegate->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($higherManager);
        $this->postJson("/api/v1/sample-requests/{$sampleRequest->id}/approve", [
            'quantity_delivered' => 5,
        ])->assertForbidden();

        $this->assertDatabaseHas('sample_requests', ['id' => $sampleRequest->id, 'status' => 'pending']);
        $this->assertSame(0, SampleStock::count());
        $this->assertSame(0, SampleMovement::count());
    }
}