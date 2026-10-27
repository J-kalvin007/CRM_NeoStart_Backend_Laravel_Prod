<?php

namespace Tests\Feature;

use App\Models\{Activity, Country, Objectif, Role, User};
use App\Services\BusinessReportExports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class BusinessReportExportsTest extends TestCase
{
    use RefreshDatabase;

    private function workbookRows($response): array
    {
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'export-test-');
        file_put_contents($path, $response->getContent());
        try {
            $book = IOFactory::load($path); $rows = $book->getSheet(1)->toArray(); $book->disconnectWorksheets();
            return array_slice($rows, 1);
        } finally { unlink($path); }
    }

    public function test_visit_exports_obey_hierarchy_selected_country_and_report_privacy_and_exclude_leave(): void
    {
        foreach (['superviseur', 'delegue-medical'] as $role) Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $togo = Country::create(['name' => 'Togo', 'iso_code' => 'TG']);
        $benin = Country::create(['name' => 'Bénin', 'iso_code' => 'BJ']);
        $manager = User::factory()->create(['country_id' => $togo->id]); $manager->assignRole('superviseur');
        $own = User::factory()->create(['country_id' => $togo->id, 'manager_id' => $manager->id]);
        $other = User::factory()->create(['country_id' => $togo->id]);
        $foreign = User::factory()->create(['country_id' => $benin->id, 'manager_id' => $manager->id]);
        foreach ([[$own, 'draft', 'SECRET BROUILLON'], [$own, 'submitted', 'CONTENU COMPLET'], [$other, 'submitted', 'AUTRE EQUIPE'], [$foreign, 'submitted', 'BENIN']] as [$owner, $status, $content]) {
            Activity::create(['user_id' => $owner->id, 'activity_type' => 'visite', 'date_activite' => now(), 'statut' => 'realisee', 'report_status' => $status, 'report_content' => $content]);
        }
        Activity::create(['user_id' => $own->id, 'activity_type' => 'conge', 'date_activite' => now(), 'statut' => 'realisee']);
        Sanctum::actingAs($manager);
        $rows = $this->workbookRows($this->withHeader('X-Country-Id', $togo->id)->get('/api/v1/export/activities'));
        $this->assertCount(2, $rows);
        $text = json_encode($rows); $this->assertStringContainsString('CONTENU COMPLET', $text);
        foreach (['SECRET BROUILLON', 'AUTRE EQUIPE', 'BENIN'] as $secret) $this->assertStringNotContainsString($secret, $text);
        $submitted = $this->workbookRows($this->get('/api/v1/export/activities?reports_only=1'));
        $this->assertCount(1, $submitted);
        $foreignRows = $this->workbookRows($this->withHeader('X-Country-Id', $benin->id)->get('/api/v1/export/activities'));
        $this->assertCount(1, $foreignRows); $this->assertStringContainsString('BENIN', json_encode($foreignRows));
    }

    public function test_performance_export_contains_actual_objectives_instead_of_synthetic_targets(): void
    {
        $user = User::factory()->create();
        Objectif::create(['user_id' => $user->id, 'date_debut' => now()->startOfMonth(), 'date_fin' => now()->endOfMonth(),
            'valeur_cible' => 10, 'valeur_realisee' => 15, 'statut' => 'depasse']);
        Sanctum::actingAs($user);
        $rows = $this->workbookRows($this->get('/api/v1/export/performance'));
        $this->assertCount(1, $rows); $this->assertSame($user->name, $rows[0][0]);
        $this->assertEquals(150, $rows[0][6]);
        $this->get('/api/v1/export/performance?format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
