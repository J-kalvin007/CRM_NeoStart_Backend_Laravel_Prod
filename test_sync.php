<?php

// 1. Initial Login to trigger OTP
$ch = curl_init('http://localhost:8000/api/v1/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => 'admin@crm.local', 'password' => 'password']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$userId = $data['user_id'] ?? null;

if (!$userId) {
    echo "Login step 1 failed. Response: " . $response . "\n";
    exit(1);
}

// 2. Fetch OTP from Laravel Cache
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$otpCode = \Illuminate\Support\Facades\Cache::get('login_otp_' . $userId);
if (!$otpCode) {
    echo "Failed to retrieve OTP from Cache for user: $userId\n";
    exit(1);
}

// 3. Verify OTP
$ch = curl_init('http://localhost:8000/api/v1/verify-otp');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['user_id' => $userId, 'otp_code' => $otpCode]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
$otpResponse = curl_exec($ch);
curl_close($ch);

$otpData = json_decode($otpResponse, true);
$token = $otpData['token'] ?? null;

if (!$token) {
    echo "OTP Verification failed.\n";
    exit(1);
}
echo "✅ Authentifié avec succès.\n\n";

$baseUrl = "http://localhost:8000/api/v1";
$headers = [
    "Authorization: Bearer " . $token,
    "Content-Type: application/json",
    "Accept: application/json"
];

function makeRequest($method, $url, $data = null) {
    global $headers;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $httpCode, 'data' => json_decode($response, true)];
}

echo "--- TEST SYNC PULL (Récupérer ce qui a changé depuis 1h) ---\n";
$lastSync = date('Y-m-d H:i:s', strtotime('-1 hour'));
$resPull = makeRequest('GET', "$baseUrl/sync/pull?last_sync=" . urlencode($lastSync));

if ($resPull['status'] == 200) {
    echo "✅ PULL réussi. Données reçues depuis $lastSync :\n";
    echo "- Contacts modifiés : " . count($resPull['data']['data']['contacts']) . "\n";
    echo "- Tâches modifiées : " . count($resPull['data']['data']['tasks']) . "\n";
} else {
    echo "❌ Échec du PULL.\n"; print_r($resPull);
}
echo "\n";

echo "--- TEST SYNC PUSH (Envoyer des actions Offline) ---\n";
$resAcc = makeRequest('GET', "$baseUrl/accounts");
$accountId = $resAcc['data']['data'][0]['id'] ?? null;

// On simule que le mobile a généré son propre UUID et a créé une tâche hors-ligne
$offlineUuid = \Illuminate\Support\Str::uuid()->toString();
$pushData = [
    'tasks' => [
        [
            'id' => $offlineUuid, // UUID généré hors-ligne par le mobile
            'title' => 'Visite hors ligne Dr Koffi',
            'type' => 'visit',
            'status' => 'planned',
            'start_time' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'account_id' => $accountId
        ]
    ]
];

$resPush = makeRequest('POST', "$baseUrl/sync/push", $pushData);

if ($resPush['status'] == 200) {
    echo "✅ PUSH réussi.\n";
    echo "- Tâches traitées : " . $resPush['data']['processed']['tasks'] . "\n";
    echo "- Timestamp du serveur : " . $resPush['data']['timestamp'] . "\n";
} else {
    echo "❌ Échec du PUSH.\n"; print_r($resPush);
}

echo "\n=== TESTS DE SYNCHRONISATION TERMINÉS ===\n";
