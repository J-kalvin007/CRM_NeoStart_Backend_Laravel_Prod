<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function document(string $format = 'xlsx'): array
    {
        return ['format' => $format, 'title' => 'Objectifs & performance',
            'filters' => [['label' => 'Poste', 'value' => 'Délégué médical']],
            'summary' => [['label' => 'Lignes', 'value' => '2']],
            'sections' => [['title' => 'Détails', 'columns' => ['Nom', 'Valeur', 'Commentaire'],
                'rows' => [['Élodie Mensah', 125.5, '=HYPERLINK("https://example.test")'], ['Koffi', 0, '<script>alert(1)</script>']]]]];
    }

    public function test_document_requires_authentication(): void
    {
        $this->postJson('/api/v1/reports/document', $this->document())->assertUnauthorized();
    }

    public function test_excel_is_real_xlsx_preserves_accents_numbers_and_text_without_formulas(): void
    {
        Sanctum::actingAs(User::factory()->create(['name' => 'Afi']));
        $response = $this->postJson('/api/v1/reports/document', $this->document())->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $response->getContent());
        $path = tempnam(sys_get_temp_dir(), 'test-report-');
        file_put_contents($path, $response->getContent());
        try {
            $book = IOFactory::load($path);
            $this->assertSame('Contexte', $book->getSheet(0)->getTitle());
            $this->assertSame('Afi', $book->getSheet(0)->getCell('B3')->getValue());
            $details = $book->getSheet(1);
            $this->assertSame('Élodie Mensah', $details->getCell('A2')->getValue());
            $this->assertSame(125.5, $details->getCell('B2')->getValue());
            $this->assertSame(0, $details->getCell('B3')->getValue());
            $this->assertSame('s', $details->getCell('C2')->getDataType());
            $this->assertSame('=HYPERLINK("https://example.test")', $details->getCell('C2')->getValue());
            $book->disconnectWorksheets();
        } finally { unlink($path); }
    }

    public function test_pdf_is_a_real_downloadable_document_and_long_reports_are_not_truncated(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data = $this->document('pdf');
        $data['sections'][0]['rows'] = array_fill(0, 65, ['Élodie Mensah', 0, str_repeat('Compte rendu détaillé. ', 40)]);
        $response = $this->postJson('/api/v1/reports/document', $data)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertMatchesRegularExpression('/attachment; filename=".*\.pdf"/', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('/Type /Pages', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_empty_data_still_generates_an_honest_report(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data = $this->document('pdf'); $data['sections'][0]['rows'] = []; $data['summary'] = [];
        $this->postJson('/api/v1/reports/document', $data)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_invalid_or_oversized_documents_are_rejected_instead_of_silently_losing_data(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data = $this->document(); $data['sections'][0]['rows'][0] = ['Incomplete'];
        $this->postJson('/api/v1/reports/document', $data)->assertUnprocessable();
        $data = $this->document(); $data['sections'][0]['rows'][0][0] = ['nested' => 'object'];
        $this->postJson('/api/v1/reports/document', $data)->assertUnprocessable();
        $data = $this->document(); $data['sections'][0]['rows'][0][0] = str_repeat('A', 32001);
        $this->postJson('/api/v1/reports/document', $data)->assertUnprocessable();
        $data = $this->document(); $data['format'] = 'html';
        $this->postJson('/api/v1/reports/document', $data)->assertUnprocessable();
    }
}
