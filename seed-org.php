<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OrganizationalUnit;
use App\Models\OrganizationConfig;
use App\Models\HierarchyLevelDefinition;

$configId = 'd2dd3c29-e7c5-478e-846f-61c53fe91744';
$config = OrganizationConfig::find($configId);

if (!$config) {
    echo "Config not found\n";
    // Let's create it since maybe the user gave a UUID that doesn't exist yet but created it in frontend
    $config = OrganizationConfig::create([
        'id' => $configId,
        'name' => 'Configuration Hiérarchie',
        'is_active' => true,
    ]);
}

// Ensure levels exist or create them
$level1 = HierarchyLevelDefinition::firstOrCreate([
    'organization_config_id' => $configId,
    'level_order' => 1
], [
    'name' => 'Région',
    'code' => 'REGION',
    'type' => 'geographical',
    'is_active' => true,
]);

$level2 = HierarchyLevelDefinition::firstOrCreate([
    'organization_config_id' => $configId,
    'level_order' => 2
], [
    'name' => 'Secteur',
    'code' => 'SECTOR',
    'type' => 'geographical',
    'is_active' => true,
]);

// Create some units
$regionNord = OrganizationalUnit::create([
    'name' => 'Région Nord',
    'type' => 'REGION',
    'organization_config_id' => $configId,
    'level_definition_id' => $level1->id,
    'level_order' => 1,
    'level_code' => 'REGION',
    'is_active' => true,
]);

$secteurLille = OrganizationalUnit::create([
    'name' => 'Secteur Lille',
    'type' => 'SECTOR',
    'parent_id' => $regionNord->id,
    'organization_config_id' => $configId,
    'level_definition_id' => $level2->id,
    'level_order' => 2,
    'level_code' => 'SECTOR',
    'is_active' => true,
]);

$regionSud = OrganizationalUnit::create([
    'name' => 'Région Sud',
    'type' => 'REGION',
    'organization_config_id' => $configId,
    'level_definition_id' => $level1->id,
    'level_order' => 1,
    'level_code' => 'REGION',
    'is_active' => true,
]);

$secteurMarseille = OrganizationalUnit::create([
    'name' => 'Secteur Marseille',
    'type' => 'SECTOR',
    'parent_id' => $regionSud->id,
    'organization_config_id' => $configId,
    'level_definition_id' => $level2->id,
    'level_order' => 2,
    'level_code' => 'SECTOR',
    'is_active' => true,
]);

echo "Created units for " . $configId . "\n";
