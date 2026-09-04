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


echo "--- TEST WORKFLOW : NOTES DE FRAIS (EXPENSES) ---\n";

// 1. CREATE
echo "\n[1. DRAFT] Création d'une note de frais de Repas...\n";
$createData = [
    'title' => 'Déjeuner d\'affaires avec le Dr. Diop',
    'type' => 'meal',
    'amount' => 15000,
    'currency' => 'XOF',
    'expense_date' => date('Y-m-d')
];

$resCreate = makeRequest('POST', "$baseUrl/expenses", $createData);
if ($resCreate['status'] == 201) {
    $expenseId = $resCreate['data']['id'];
    echo "✅ Note de frais créée. (Status: " . $resCreate['data']['status'] . ")\n";
} else {
    echo "❌ Échec de la création.\n"; print_r($resCreate['data']);
    exit(1);
}

// 2. SUBMIT
echo "\n[2. SUBMIT] Soumission de la note de frais pour validation...\n";
$resSubmit = makeRequest('POST', "$baseUrl/expenses/$expenseId/submit");
if ($resSubmit['status'] == 200) {
    echo "✅ Note soumise avec succès. (Status actuel: " . $resSubmit['data']['status'] . ")\n";
} else {
    echo "❌ Échec de la soumission.\n";
}

// 3. TRY TO EDIT (Doit échouer car soumis)
echo "\n[3. SECURITE] Tentative de modification après soumission...\n";
$resUpdate = makeRequest('PUT', "$baseUrl/expenses/$expenseId", ['amount' => 20000]);
if ($resUpdate['status'] == 403) {
    echo "✅ Modification rejetée correctement (Code 403). Le système de verrouillage fonctionne.\n";
} else {
    echo "❌ AVERTISSEMENT : La note a pu être modifiée alors qu'elle est soumise !\n";
}

echo "\n=== TEST TERMINÉ ===\n";
