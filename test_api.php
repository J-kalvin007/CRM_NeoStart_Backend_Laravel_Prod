<?php

$ch = curl_init('http://localhost:8000/api/v1/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => 'admin@crm.local', 'password' => 'password']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$token = $data['token'] ?? null;

if (!$token) {
    echo "Login failed. Response: " . $response . "\n";
    exit(1);
}

echo "Login successful.\n";

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

