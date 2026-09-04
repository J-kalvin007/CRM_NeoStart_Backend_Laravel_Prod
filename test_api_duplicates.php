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
echo "Step 1: Login successful. User ID: $userId\n";

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
echo "Step 2: OTP retrieved from Cache: $otpCode\n";

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
    echo "OTP Verification failed. Response: " . $otpResponse . "\n";
    exit(1);
}
echo "Step 3: OTP verified successfully. Token acquired.\n";

$baseUrl = "http://localhost:8000/api/v1";
$headers = [
    "Authorization: Bearer " . $token,
    "Content-Type: application/json",
    "Accept: application/json"
];

function testDuplicatePost($url, $data) {
    global $headers;
    echo "Testing POST $url...\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Status: $httpCode\n";
    echo "Response:\n";
    echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    echo str_repeat("-", 50) . "\n";
}

$ch = curl_init("$baseUrl/accounts");
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
$accounts = json_decode($response, true)['data'] ?? [];

if (empty($accounts)) {
    die("Aucun compte trouvé\n");
}

$firstAccount = $accounts[0];
$subsidiaryId = $firstAccount['subsidiary']['id'];
$accountId = $firstAccount['id'];

$accountData = [
    'subsidiary_id' => $subsidiaryId,
    'nom' => $firstAccount['nom'],
    'ville' => $firstAccount['ville'],
    'telephone' => $firstAccount['telephone'],
    'email' => $firstAccount['email']
];

echo "\n--- Test Duplicata Compte ---\n";
testDuplicatePost("$baseUrl/accounts/check-duplicates", $accountData);

$ch = curl_init("$baseUrl/contacts");
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
$contacts = json_decode($response, true)['data'] ?? [];
$firstContact = $contacts[0];

$contactData = [
    'account_id' => $firstContact['account']['id'] ?? $accountId,
    'nom' => $firstContact['nom'],
    'prenom' => $firstContact['prenom'],
    'telephone' => $firstContact['telephone'],
    'email' => $firstContact['email']
];

echo "\n--- Test Duplicata Contact ---\n";
testDuplicatePost("$baseUrl/contacts/check-duplicates", $contactData);
