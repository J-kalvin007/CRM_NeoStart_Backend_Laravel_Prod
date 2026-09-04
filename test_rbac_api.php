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


echo "--- TEST API : ROLES ET PERMISSIONS (RBAC) ---\n";

// 1. GET Permissions
echo "\n[1. READ] Récupération de la liste des permissions...\n";
$resPerms = makeRequest('GET', "$baseUrl/permissions");
if ($resPerms['status'] == 200) {
    echo "✅ Liste des permissions récupérée avec succès. (Total: " . count($resPerms['data']) . ")\n";
} else {
    echo "❌ Échec de la récupération des permissions.\n";
}

// 2. CREATE Role
echo "\n[2. CREATE] Création d'un nouveau rôle personnalisé (Directeur Marketing)...\n";
$roleName = 'directeur-marketing-' . time();
$createData = [
    'name' => $roleName,
    'permissions' => ['create-expense', 'approve-expense'] // Permissions créées au test précédent
];

$resCreate = makeRequest('POST', "$baseUrl/roles", $createData);
if ($resCreate['status'] == 201) {
    $roleId = $resCreate['data']['id'];
    echo "✅ Rôle créé avec succès (ID: $roleId)\n";
    echo "   - Permissions assignées : " . count($resCreate['data']['permissions']) . "\n";
} else {
    echo "❌ Échec de la création du rôle.\n"; print_r($resCreate['data']);
    exit(1);
}

// 3. READ (SHOW)
echo "\n[3. READ] Lecture du rôle...\n";
$resShow = makeRequest('GET', "$baseUrl/roles/$roleId");
if ($resShow['status'] == 200) {
    echo "✅ Lecture réussie. Nom du rôle: " . $resShow['data']['name'] . "\n";
} else {
    echo "❌ Échec de la lecture.\n";
}

// 4. UPDATE Role
echo "\n[4. UPDATE] Mise à jour du rôle (Retrait d'une permission)...\n";
$updateData = [
    'permissions' => ['approve-expense']
];
$resUpdate = makeRequest('PUT', "$baseUrl/roles/$roleId", $updateData);
if ($resUpdate['status'] == 200) {
    echo "✅ Mise à jour réussie. Le rôle n'a plus que " . count($resUpdate['data']['permissions']) . " permission(s).\n";
} else {
    echo "❌ Échec de la mise à jour.\n";
}

// 5. DELETE Role
echo "\n[5. DELETE] Suppression du rôle...\n";
$resDelete = makeRequest('DELETE', "$baseUrl/roles/$roleId");
if ($resDelete['status'] == 204) {
    echo "✅ Suppression réussie. (Status 204)\n";
} else {
    echo "❌ Échec de la suppression.\n"; print_r($resDelete);
}

echo "\n=== TEST TERMINÉ ===\n";
