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

function testEndpoint($url, $token, $method, $data = []) {
    echo "Testing $method $url...\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json']);
    
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $resData = json_decode($res, true);
    $prettyRes = json_encode($resData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "HTTP Status: $httpCode\n";
    echo "Response:\n" . $prettyRes . "\n\n";
    echo "--------------------------------------------------\n";
    
    return $resData;
}

// CREATE Account
$accountData = [
    'nom' => 'Clinique des Champs-Élysées (Test API)',
    'account_type_id' => \App\Models\AccountType::where('code', 'HOSP')->first()->id ?? null,
    'subsidiary_id' => \App\Models\Subsidiary::first()->id ?? null,
    'email' => 'contact@clinique-champs.fr',
    'telephone' => '01 02 03 04 05',
    'adresse' => '61 Avenue des Champs-Élysées',
    'ville' => 'Paris',
    'gps_lat' => 48.870,
    'gps_lng' => 2.305,
    'statut' => 'actif',
    'meta' => ['capacite_lits' => 50, 'secteur' => 'Privé']
];
$newAccount = testEndpoint('http://localhost:8000/api/v1/accounts', $token, 'POST', $accountData);

// UPDATE Account
if (isset($newAccount['data']['id'])) {
    $updateData = [
        'nom' => 'Clinique des Champs-Élysées (Modifié API)'
    ];
    testEndpoint('http://localhost:8000/api/v1/accounts/' . $newAccount['data']['id'], $token, 'PUT', $updateData);
}

// CREATE Contact
$contactData = [
    'nom' => 'Martin',
    'prenom' => 'Alice',
    'contact_type_id' => \App\Models\ContactType::where('code', 'MED')->first()->id ?? null,
    'account_id' => $newAccount['data']['id'] ?? (\App\Models\Account::first()->id ?? null),
    'email' => 'alice.martin@clinique-champs.fr',
    'telephone' => '06 11 22 33 44',
    'statut' => 'actif',
    'meta' => ['rpps' => '88889999000', 'specialite' => 'Dermatologie']
];
$newContact = testEndpoint('http://localhost:8000/api/v1/contacts', $token, 'POST', $contactData);

// UPDATE Contact
if (isset($newContact['data']['id'])) {
    $updateData = [
        'telephone' => '06 11 22 33 55'
    ];
    testEndpoint('http://localhost:8000/api/v1/contacts/' . $newContact['data']['id'], $token, 'PUT', $updateData);
}

// CREATE Activity
$tourPlan = \App\Models\TourPlan::first();
$activityData = [
    'tour_plan_id' => $tourPlan->id ?? null,
    'contact_id' => $newContact['data']['id'] ?? (\App\Models\Contact::first()->id ?? null),
    'activity_type' => 'visite',
    'date_activite' => \Carbon\Carbon::now()->addDays(5)->toDateString(),
    'statut' => 'planifiee',
    'notes' => 'Test Visite POST API'
];
$newActivity = testEndpoint('http://localhost:8000/api/v1/activities', $token, 'POST', $activityData);

// UPDATE Activity
if (isset($newActivity['data']['id'])) {
    $updateData = [
        'statut' => 'cloture',
        'gps_fin_lat' => 48.871,
        'gps_fin_lng' => 2.304,
        'notes' => 'Visite Clôturée via API'
    ];
    testEndpoint('http://localhost:8000/api/v1/activities/' . $newActivity['data']['id'], $token, 'PUT', $updateData);
}
