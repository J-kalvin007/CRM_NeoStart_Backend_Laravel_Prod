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


echo "--- TEST CRUD : CUSTOM FIELDS (Domaine Médical) ---\n";

// 1. CREATE
echo "\n[CREATE] Ajout d'un champ personnalisé 'Capacité en lits' pour les Hôpitaux (Accounts)...\n";
$createData = [
    'model_type' => 'App\Models\Account',
    'name' => 'capacite_lits_' . time(), // Pour éviter les doublons avec les contraintes d'unicité
    'label' => 'Capacité en lits (Hospitalisation)',
    'type' => 'number',
    'is_required' => false,
    'order' => 1
];

$resCreate = makeRequest('POST', "$baseUrl/custom-fields", $createData);
if ($resCreate['status'] == 201) {
    $fieldId = $resCreate['data']['id'];
    echo "✅ Champ créé avec succès (ID: $fieldId)\n";
} else {
    echo "❌ Échec de la création.\n"; print_r($resCreate['data']);
    exit(1);
}

// 2. READ (SHOW)
echo "\n[READ] Lecture du champ personnalisé...\n";
$resShow = makeRequest('GET', "$baseUrl/custom-fields/$fieldId");
if ($resShow['status'] == 200) {
    echo "✅ Lecture réussie. Label: " . $resShow['data']['label'] . " (Type: " . $resShow['data']['type'] . ")\n";
} else {
    echo "❌ Échec de la lecture.\n";
}

// 3. UPDATE
echo "\n[UPDATE] Mise à jour du champ (le rendre requis)...\n";
$updateData = [
    'is_required' => true,
    'order' => 2
];
$resUpdate = makeRequest('PUT', "$baseUrl/custom-fields/$fieldId", $updateData);
if ($resUpdate['status'] == 200) {
    echo "✅ Mise à jour réussie. (Requis: " . ($resUpdate['data']['is_required'] ? 'Oui' : 'Non') . ")\n";
} else {
    echo "❌ Échec de la mise à jour.\n";
}

// 4. DELETE
echo "\n[DELETE] Suppression du champ personnalisé...\n";
$resDelete = makeRequest('DELETE', "$baseUrl/custom-fields/$fieldId");
if ($resDelete['status'] == 204) {
    echo "✅ Suppression réussie. (Status 204)\n";
} else {
    echo "❌ Échec de la suppression.\n"; print_r($resDelete);
}

echo "\n=== TEST TERMINÉ ===\n";
