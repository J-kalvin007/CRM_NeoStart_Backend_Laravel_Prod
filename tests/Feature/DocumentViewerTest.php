<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentViewerTest extends TestCase
{
    use RefreshDatabase;

    private function zipPackage(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pkg') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('deck/index.html', '<html><body>Diapo</body></html>');
        $zip->addFromString('../evil.txt', 'x');
        $zip->close();

        return new UploadedFile($path, 'deck.zip', 'application/zip', null, true);
    }

    public function test_html5_package_is_extracted_and_exposed_as_viewer_url(): void
    {
        Storage::fake('public');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('superviseur');
        Sanctum::actingAs($user);
        $product = Product::create(['nom' => 'Cardio', 'code' => 'CARD', 'prix' => 1000]);

        $response = $this->post('/api/v1/documents', [
            'documentable_id' => $product->id,
            'documentable_type' => 'App\\Models\\Product',
            'title' => 'Deck HTML5',
            'type' => 'presentation',
            'file' => $this->zipPackage(),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('format', 'zip')
            ->assertJsonPath('viewer_format', 'html')
            ->assertJsonPath('produit', 'Cardio');
        $this->assertStringEndsWith('/deck/index.html', $response->json('viewer_url'));
        $this->assertStringStartsWith('http://localhost/storage/docs/', $response->json('url'));

        $doc = Document::withoutGlobalScopes()->first();
        Storage::disk('public')->assertExists($doc->viewer_path);
        $this->assertFalse(Storage::disk('public')->exists('docs/viewer/evil.txt'));
    }

    public function test_presentation_without_file_on_disk_has_no_viewer(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::create(['nom' => 'Cardio', 'code' => 'CARD', 'prix' => 1000]);
        $doc = Document::create(['documentable_id' => $product->id, 'documentable_type' => Product::class, 'title' => 'Deck', 'type' => 'presentation', 'file_path' => 'docs/absent.pptx', 'is_active' => true]);
        app(\App\Services\DocumentViewerService::class)->prepare($doc);

        $this->assertNull($doc->fresh()->viewer_path);
    }
    public function test_document_listing_uses_the_exact_product_assignment(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());
        $first = Product::create(['nom' => 'CEDIN 300', 'code' => 'CEDIN300', 'prix' => 1000]);
        $other = Product::create(['nom' => 'CEDIN 125', 'code' => 'CEDIN125', 'prix' => 1000]);
        foreach ([$first, $other] as $product) {
            Document::create(['documentable_id' => $product->id, 'documentable_type' => Product::class,
                'title' => 'CEDIN présentation', 'type' => 'presentation', 'file_path' => 'docs/deck.pdf']);
        }
        $this->getJson('/api/v1/documents?product_id=' . $first->id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.product_id', $first->id);
        $this->getJson('/api/v1/documents?documentable_type=' . urlencode(Product::class) . '&documentable_id=' . $other->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.product_id', $other->id);
        $this->getJson('/api/v1/documents?documentable_type=' . urlencode(Product::class))->assertUnprocessable();
    }
}
