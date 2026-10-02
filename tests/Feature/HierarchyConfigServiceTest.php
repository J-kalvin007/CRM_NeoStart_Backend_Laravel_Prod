<?php

namespace Tests\Feature;

use App\Services\HierarchyConfigService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HierarchyConfigServiceTest extends TestCase
{
    public function test_it_generates_a_unique_code_when_creating_an_organization_configuration(): void
    {
        Artisan::call('migrate:fresh');

        $this->assertTrue(Schema::hasTable('organization_configs'));

        $service = app(HierarchyConfigService::class);

        $config = $service->createOrganizationConfig([
            'name' => 'Skinity',
            'description' => 'Test configuration',
            'is_active' => true,
            'levels' => [
                [
                    'level_order' => 1,
                    'level_code' => 'ROOT',
                    'level_name' => 'Racine',
                    'level_description' => 'Niveau initial',
                    'can_have_users' => true,
                    'can_have_managers' => true,
                    'is_active' => true,
                ],
            ],
        ]);

        $this->assertSame('skinity', $config->code);
        $this->assertDatabaseHas('organization_configs', ['code' => 'skinity']);
    }
}
