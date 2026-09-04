<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Subsidiary;
use App\Models\Region;
use App\Models\Territory;
use App\Models\Zone;
use App\Models\Sector;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

// 1. Setup basic data for the test
$sub = Subsidiary::first();
$region = Region::firstOrCreate(['name' => 'Test Region'], ['subsidiary_id' => $sub->id, 'code' => 'REG1']);
$territory = Territory::firstOrCreate(['name' => 'Test Territory'], ['region_id' => $region->id, 'code' => 'TER1']);
$zone = Zone::firstOrCreate(['name' => 'Test Zone'], ['territory_id' => $territory->id, 'code' => 'ZON1']);
$sector = Sector::firstOrCreate(['name' => 'Test Sector'], ['zone_id' => $zone->id, 'code' => 'SEC1']);

$admin = User::firstOrCreate(
    ['email' => 'admin_hierarchy@example.com'],
    [
        'name' => 'Admin Hierarchy',
        'password' => Hash::make('password'),
        'subsidiary_id' => $sub->id,
        'email_verified_at' => now(),
        'force_password_change' => false
    ]
);

$token = $admin->createToken('test')->plainTextToken;
$baseUrl = 'http://localhost:8000/api/v1';

function makeApiRequest($method, $url, $data, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'data' => json_decode($response, true)];
}

echo "=== TEST AUTH INVITE ===\n";
$inviteData = [
    'name' => 'Test User Deep Hierarchy',
    'email' => 'user_hierarchy_' . time() . '@example.com',
    'password' => 'password123',
    'subsidiary_id' => $sub->id,
    'region_id' => $region->id,
    'territory_id' => $territory->id,
    'zone_id' => $zone->id,
    'sector_id' => $sector->id
];

$inviteRes = makeApiRequest('POST', $baseUrl . '/auth/invite', $inviteData, $token);
if ($inviteRes['status'] == 201) {
    echo "✅ Success Auth Invite! User created with deep hierarchy.\n";
    echo "Sector ID: " . $inviteRes['data']['user']['sector_id'] . "\n";
} else {
    echo "❌ Failed Auth Invite. Status: " . $inviteRes['status'] . "\n";
    print_r($inviteRes['data']);
}

echo "\n=== TEST CREATE TEAM ===\n";
$teamData = [
    'name' => 'Team Alpha ' . time(),
    'sector_id' => $sector->id
];

$teamRes = makeApiRequest('POST', $baseUrl . '/teams', $teamData, $token);
if ($teamRes['status'] == 201) {
    echo "✅ Success Create Team! Team created with sector_id.\n";
    $teamId = $teamRes['data']['data']['id'];
} else {
    echo "❌ Failed Create Team. Status: " . $teamRes['status'] . "\n";
    print_r($teamRes['data']);
}

echo "\n=== TEST UPDATE TEAM ===\n";
if (isset($teamId)) {
    $updateTeamData = [
        'name' => 'Team Alpha Updated ' . time(),
        'sector_id' => $sector->id
    ];
    $updateTeamRes = makeApiRequest('PUT', $baseUrl . '/teams/' . $teamId, $updateTeamData, $token);
    if ($updateTeamRes['status'] == 200) {
        echo "✅ Success Update Team! Team updated.\n";
    } else {
        echo "❌ Failed Update Team. Status: " . $updateTeamRes['status'] . "\n";
        print_r($updateTeamRes['data']);
    }
}
