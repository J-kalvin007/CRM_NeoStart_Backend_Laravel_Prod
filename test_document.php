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

// Trouver un produit pour y attacher le document
$resProd = makeRequest('GET', "$baseUrl/products");
$productId = $resProd['data']['data'][0]['id'] ?? null;

if (!$productId) {
    die("❌ Aucun produit trouvé.\n");
}
echo "Produit trouvé: $productId\n";

// TEST CRUD : DOCUMENT (Polymorphique)
echo "--- TEST DES DOCUMENTS (GED) ---\n";
$docData = [
    'documentable_id' => $productId,
    'documentable_type' => 'App\Models\Product',
    'title' => 'Brochure Produit X - V1',
    'description' => 'Brochure officielle utilisée par les délégués.',
    'type' => 'pdf',
    'file_path' => 'https://s3.amazonaws.com/crm/brochure_x.pdf',
    'version' => '1.0'
];

$resDoc = makeRequest('POST', "$baseUrl/documents", $docData);
if ($resDoc['status'] == 201) {
    $docId = $resDoc['data']['id'];
    echo "✅ Document rattaché au Produit avec succès (ID: $docId)\n";
    
    // Update Document
    $resDocUp = makeRequest('PUT', "$baseUrl/documents/$docId", ['version' => '1.1']);
    if ($resDocUp['status'] == 200) {
        echo "✅ Document mis à jour avec succès (Version: 1.1)\n";
    } else {
        echo "❌ Échec de la mise à jour.\n";
    }
} else {
    echo "❌ Échec de création du document.\n"; print_r($resDoc['data']);
}

echo "=== TEST TERMINÉ ===\n";
