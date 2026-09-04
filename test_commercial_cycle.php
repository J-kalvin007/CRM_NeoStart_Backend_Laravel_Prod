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
echo "Step 3: OTP verified successfully. Token acquired.\n\n";

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

// 2. Préparation : Obtenir un Account et un Produit
echo "2. Recherche de données existantes (Account, Produit)...\n";
$resAcc = makeRequest('GET', "$baseUrl/accounts");
$accountId = $resAcc['data']['data'][0]['id'] ?? null;

$resProd = makeRequest('GET', "$baseUrl/products");
$productId = $resProd['data']['data'][0]['id'] ?? null;

if (!$accountId || !$productId) {
    die("❌ Impossible de trouver un compte ou un produit pour faire le test.\n");
}
echo "✅ Compte trouvé : $accountId\n✅ Produit trouvé : $productId\n\n";

// 3. TEST CRUD : TASKS
echo "--- TEST DES TASKS (AGENDA) ---\n";
$taskData = [
    'title' => 'Visite Dr Kossi',
    'type' => 'visit',
    'status' => 'planned',
    'start_time' => date('Y-m-d H:i:s', strtotime('+1 day')),
    'account_id' => $accountId
];
$resTask = makeRequest('POST', "$baseUrl/tasks", $taskData);
if ($resTask['status'] == 201) {
    $taskId = $resTask['data']['id'];
    echo "✅ Task créée avec succès (ID: $taskId)\n";
    
    // Update Task (Completed)
    $resTaskUp = makeRequest('PUT', "$baseUrl/tasks/$taskId", ['status' => 'completed']);
    if ($resTaskUp['status'] == 200) {
        echo "✅ Task mise à jour avec succès (Status: completed) -> L'objectif devrait être mis à jour ! \n";
    } else {
        echo "❌ Échec de la mise à jour de la task.\n";
    }
} else {
    echo "❌ Échec de création de la task.\n"; print_r($resTask['data']);
}
echo "\n";

// 4. TEST CRUD : OPPORTUNITY
echo "--- TEST DES OPPORTUNITÉS ---\n";
$oppData = [
    'title' => 'Intérêt Produit X - Pharmacie',
    'account_id' => $accountId,
    'product_id' => $productId,
    'expected_revenue' => 15000.00,
    'probability' => 50,
    'status' => 'new'
];
$resOpp = makeRequest('POST', "$baseUrl/opportunities", $oppData);
if ($resOpp['status'] == 201) {
    $oppId = $resOpp['data']['id'];
    echo "✅ Opportunité créée avec succès (ID: $oppId)\n";
    
    // Update Opp
    $resOppUp = makeRequest('PUT', "$baseUrl/opportunities/$oppId", ['status' => 'negotiation', 'probability' => 80]);
    if ($resOppUp['status'] == 200) {
        echo "✅ Opportunité mise à jour avec succès (Status: negotiation, Prob: 80)\n";
    } else {
        echo "❌ Échec de la mise à jour de l'opportunité.\n";
    }
} else {
    echo "❌ Échec de création de l'opportunité.\n"; print_r($resOpp['data']);
}
echo "\n";

// 5. TEST CRUD : ORDER
echo "--- TEST DES COMMANDES ---\n";
if (isset($oppId)) {
    $orderData = [
        'account_id' => $accountId,
        'opportunity_id' => $oppId,
        'order_number' => 'CMD-' . time(),
        'total_amount' => 12000.00,
        'status' => 'draft',
        'order_date' => date('Y-m-d')
    ];
    $resOrder = makeRequest('POST', "$baseUrl/orders", $orderData);
    
    if ($resOrder['status'] == 201) {
        $orderId = $resOrder['data']['id'];
        echo "✅ Commande créée avec succès (ID: $orderId)\n";
        
        // Update Order
        $resOrderUp = makeRequest('PUT', "$baseUrl/orders/$orderId", ['status' => 'confirmed']);
        if ($resOrderUp['status'] == 200) {
            echo "✅ Commande confirmée avec succès (Status: confirmed)\n";
        } else {
            echo "❌ Échec de la confirmation de commande.\n";
        }
    } else {
        echo "❌ Échec de création de commande.\n"; print_r($resOrder['data']);
    }
}
echo "\n";

// 6. TEST CRUD : SALE (VENTE)
echo "--- TEST DES VENTES (FACTURATION) ---\n";
if (isset($orderId)) {
    $saleData = [
        'account_id' => $accountId,
        'order_id' => $orderId,
        'invoice_number' => 'INV-' . time(),
        'amount' => 12000.00,
        'status' => 'paid',
        'sale_date' => date('Y-m-d')
    ];
    $resSale = makeRequest('POST', "$baseUrl/sales", $saleData);
    
    if ($resSale['status'] == 201) {
        $saleId = $resSale['data']['id'];
        echo "✅ Vente/Facture créée avec succès (ID: $saleId)\n";
    } else {
        echo "❌ Échec de création de vente.\n"; print_r($resSale['data']);
    }
}
echo "\n";

echo "=== TESTS TERMINÉS ===\n";

