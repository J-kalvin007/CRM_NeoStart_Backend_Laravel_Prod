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

function testEndpoint($url, $token) {
    echo "Testing $url...\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Accept: application/json']);
    $res = curl_exec($ch);
    $resData = json_decode($res, true);
    $prettyRes = json_encode($resData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "Response:\n" . $prettyRes . "\n\n";
    echo "--------------------------------------------------\n";
}

testEndpoint('http://localhost:8000/api/v1/countries', $token);
testEndpoint('http://localhost:8000/api/v1/subsidiaries', $token);
testEndpoint('http://localhost:8000/api/v1/regions', $token);
testEndpoint('http://localhost:8000/api/v1/territories', $token);
testEndpoint('http://localhost:8000/api/v1/teams', $token);
testEndpoint('http://localhost:8000/api/v1/config/account-types', $token);
testEndpoint('http://localhost:8000/api/v1/config/contact-types', $token);
testEndpoint('http://localhost:8000/api/v1/config/product-types', $token);
testEndpoint('http://localhost:8000/api/v1/accounts', $token);
testEndpoint('http://localhost:8000/api/v1/contacts', $token);
testEndpoint('http://localhost:8000/api/v1/categories', $token);
testEndpoint('http://localhost:8000/api/v1/products', $token);
testEndpoint('http://localhost:8000/api/v1/tour-plans', $token);
testEndpoint('http://localhost:8000/api/v1/activities', $token);
testEndpoint('http://localhost:8000/api/v1/sample-movements', $token);
testEndpoint('http://localhost:8000/api/v1/objectifs', $token);

