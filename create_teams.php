<?php
use App\Models\OrganizationalUnit;
use App\Models\LevelDefinition;

$togo = OrganizationalUnit::where('name', 'like', '%Togo%')->first();
$benin = OrganizationalUnit::where('name', 'like', '%Bénin%')->orWhere('name', 'like', '%Benin%')->first();

if (!$togo || !$benin) {
    echo "Missing Togo or Benin.\n";
    return;
}

$orgConfigId = $togo->organization_config_id;
$teamLevelId = "76e1b305-2b1f-4baa-985f-a6fa581df8c3";

$teamsData = [
    ['name' => 'Équipe Lomé Centre', 'parent_id' => $togo->id],
    ['name' => 'Équipe Lomé Nord', 'parent_id' => $togo->id],
    ['name' => 'Équipe Kara', 'parent_id' => $togo->id],
    ['name' => 'Équipe Cotonou Littoral', 'parent_id' => $benin->id],
    ['name' => 'Équipe Porto-Novo', 'parent_id' => $benin->id],
    ['name' => 'Équipe Parakou', 'parent_id' => $benin->id],
];

foreach ($teamsData as $data) {
    OrganizationalUnit::firstOrCreate(
        ['name' => $data['name'], 'parent_id' => $data['parent_id']],
        [
            'organization_config_id' => $orgConfigId,
            'level_definition_id' => $teamLevelId,
            'level_order' => 7,
            'level_code' => 'team',
            'type' => 'internal',
            'is_active' => true,
        ]
    );
}

echo "Teams created successfully.\n";
