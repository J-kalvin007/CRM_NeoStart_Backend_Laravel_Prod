<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "===============================================================\n";
echo "🚀 DEMARRAGE DU TEST COMPLET END-TO-END DE LA PLATEFORME CRM 🚀\n";
echo "===============================================================\n\n";

// -------------------------------------------------------------
// 1. AUTHENTIFICATION (LOGIN + OTP)
// -------------------------------------------------------------
echo "[1] AUTHENTIFICATION\n";
$ch = curl_init('http://localhost:8000/api/v1/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => 'admin@crm.local', 'password' => 'password']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$userId = $data['user_id'] ?? null;

if (!$userId) { echo "❌ Échec du login initial.\n"; exit(1); }

$otpCode = \Illuminate\Support\Facades\Cache::get('login_otp_' . $userId);
if (!$otpCode) { echo "❌ Code OTP introuvable.\n"; exit(1); }

$ch = curl_init('http://localhost:8000/api/v1/verify-otp');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['user_id' => $userId, 'otp_code' => $otpCode]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
$otpResponse = curl_exec($ch);
curl_close($ch);

$otpData = json_decode($otpResponse, true);
$token = $otpData['token'] ?? null;
if (!$token) { echo "❌ Échec de la vérification OTP.\n"; exit(1); }
echo "✅ Authentification OTP réussie ! Token récupéré.\n\n";

// Headers globaux
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
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode($response, true);
    $resData = ($decoded && isset($decoded['data']) && count($decoded) === 1) ? $decoded['data'] : $decoded;
    // Parfois JsonResource ajoute d'autres clés comme meta/links, gérons au plus simple.
    if (isset($decoded['data']['id'])) {
         $resData = $decoded['data'];
    } elseif (isset($decoded['data']) && !isset($decoded['message'])) {
         $resData = $decoded['data'];
    }
    return ['status' => $httpCode, 'data' => $resData];
}

$meRes = makeRequest('GET', "$baseUrl/me");
$subsidiaryId = $meRes['data']['subsidiary_id'] ?? null;
if (!$subsidiaryId) { echo "❌ Impossible de récupérer le subsidiary_id de l'utilisateur connecté.\n"; exit(1); }
echo "✅ Utilisateur connecté. Subsidiary ID: $subsidiaryId\n\n";

// -------------------------------------------------------------
// 2. CONFIGURATION (Roles & Custom Fields)
// -------------------------------------------------------------
echo "[2] CONFIGURATION : ROLES ET CHAMPS PERSONNALISÉS\n";
$roleRes = makeRequest('POST', "$baseUrl/roles", ['name' => 'e2e-role-'.time(), 'permissions' => []]);
if ($roleRes['status'] == 201) echo "✅ Rôle créé.\n";
else { echo "❌ Erreur Role: " . ($roleRes['data']['message'] ?? json_encode(['status' => $roleRes['status']])) . "\n"; exit(1); }

$cfRes = makeRequest('POST', "$baseUrl/custom-fields", [
    'model_type' => 'App\\Models\\Account', 'name' => 'cf_potentiel_'.time(), 'label' => 'Potentiel Patient',
    'type' => 'number', 'is_required' => false
]);
if ($cfRes['status'] == 201) echo "✅ Champ personnalisé créé.\n";
else { echo "❌ Erreur CustomField: " . ($cfRes['data']['message'] ?? json_encode(['status' => $cfRes['status']])) . "\n"; exit(1); }
echo "\n";

// -------------------------------------------------------------
// 3. REFERENTIEL CORE (Types, Compte, Contact, Produit)
// -------------------------------------------------------------
echo "[3] DONNEES DE BASE : COMPTES, CONTACTS ET PRODUITS\n";
$atRes = makeRequest('POST', "$baseUrl/config/account-types", ['libelle' => 'Clinique E2E', 'code' => 'CL-E2E-'.time(), 'description' => 'Test']);
if ($atRes['status'] !== 201 && $atRes['status'] !== 200) {
    echo "❌ Erreur Account Type: " . ($atRes['data']['message'] ?? json_encode($atRes)) . "\n"; exit(1);
}
$accountId = null;
if ($atRes['status'] == 201 || $atRes['status'] == 200) {
    $atId = $atRes['data']['id'] ?? (\App\Models\AccountType::first()->id);
    $accRes = makeRequest('POST', "$baseUrl/accounts", ['nom' => 'Hopital E2E-'.time(), 'account_type_id' => $atId, 'subsidiary_id' => $subsidiaryId]);
    if ($accRes['status'] == 201) {
        $accountId = $accRes['data']['id'];
        echo "✅ Compte (Hôpital) créé.\n";
    }
}
if (!$accountId) { echo "❌ Erreur Compte: " . ($accRes['data']['message'] ?? json_encode(['status' => $accRes['status'], 'message' => $accRes['data']['message'] ?? ''])) . "\n"; exit(1); }

$ctRes = makeRequest('POST', "$baseUrl/config/contact-types", ['libelle' => 'Médecin E2E', 'code' => 'MD-E2E-'.time()]);
$contactId = null;
if ($ctRes['status'] == 201 || $ctRes['status'] == 200) {
    $ctId = $ctRes['data']['id'] ?? (\App\Models\ContactType::first()->id);
    $conRes = makeRequest('POST', "$baseUrl/contacts", ['prenom' => 'Jean', 'nom' => 'E2E-'.time(), 'account_id' => $accountId, 'contact_type_id' => $ctId, 'subsidiary_id' => $subsidiaryId]);
    if ($conRes['status'] == 201) {
        $contactId = $conRes['data']['id'];
        echo "✅ Contact (Médecin) créé.\n";
    }
}
if (!$contactId) { echo "❌ Erreur Contact: " . ($conRes['data']['message'] ?? json_encode($conRes)) . "\n"; exit(1); }

$ptRes = makeRequest('POST', "$baseUrl/config/product-types", ['libelle' => 'Médicament E2E', 'code' => 'PR-E2E-'.time()]);
$productId = null;
if ($ptRes['status'] == 201 || $ptRes['status'] == 200) {
    $ptId = $ptRes['data']['id'] ?? (\App\Models\ProductType::first()->id);
    $prodRes = makeRequest('POST', "$baseUrl/products", ['nom' => 'Paracétamol E2E-'.time(), 'product_type_id' => $ptId, 'prix_unitaire' => 1000, 'subsidiary_id' => $subsidiaryId]);
    if ($prodRes['status'] == 201) {
        $productId = $prodRes['data']['id'];
        echo "✅ Produit créé.\n";
    }
}
if (!$productId) { echo "❌ Erreur Produit: " . ($prodRes['data']['message'] ?? json_encode(['status' => $prodRes['status'], 'message' => $prodRes['data']['message'] ?? ''])) . "\n"; exit(1); }
echo "\n";

// -------------------------------------------------------------
// 4. SFA : PLANIFICATION & VISITE (Tour Plan -> Activity)
// -------------------------------------------------------------
echo "[4] FORCE DE VENTE (SFA) : PLANIFICATION ET VISITE\n";
$tpRes = makeRequest('POST', "$baseUrl/tour-plans", [
    'nom' => 'Tournée Dakar E2E',
    'date_debut' => date('Y-m-d'), 'date_fin' => date('Y-m-d'),
    'statut' => 'brouillon', 'subsidiary_id' => $subsidiaryId
]);
$tourPlanId = null;
if ($tpRes['status'] == 201) {
    $tourPlanId = $tpRes['data']['id'];
    echo "✅ Tournée planifiée.\n";
} else { echo "❌ Erreur TourPlan: " . ($tpRes['data']['message'] ?? ''); exit(1); }

$actRes = makeRequest('POST', "$baseUrl/activities", [
    'tour_plan_id' => $tourPlanId, 'account_id' => $accountId, 'contact_id' => $contactId,
    'notes' => 'Visite Dr Jean', 'activity_type' => 'visite',
    'statut' => 'realisee', // Simulation d'une visite terminée
    'date_activite' => date('Y-m-d'), 'heure_debut' => date('H:i'), 'heure_fin' => date('H:i', strtotime('+1 hour')),
    'subsidiary_id' => $subsidiaryId
]);
$activityId = null;
if ($actRes['status'] == 201) {
    $activityId = $actRes['data']['id'];
    echo "✅ Activité (Visite) réalisée.\n";
} else { echo "❌ Erreur Activité: " . ($actRes['data']['message'] ?? ''); exit(1); }
echo "\n";

// -------------------------------------------------------------
// 5. TRANSACTIONS METIER : Echantillon & Opportunité & Frais
// -------------------------------------------------------------
echo "[5] TRANSACTIONS (Echantillons, Opportunité, GED, Frais)\n";
// Echantillon
$smRes = makeRequest('POST', "$baseUrl/sample-movements", [
    'product_id' => $productId, 'movement_type' => 'distribution', 'quantity' => 2,
    'activity_id' => $activityId, 'notes' => 'Remis lors de la visite E2E',
    'subsidiary_id' => $subsidiaryId
]);
if ($smRes['status'] == 201) echo "✅ Échantillon remis.\n";
else { echo "❌ Erreur Echantillon: " . ($smRes['data']['message'] ?? json_encode(['status' => $smRes['status']])) . "\n"; exit(1); }

// Opportunité
$oppRes = makeRequest('POST', "$baseUrl/opportunities", [
    'account_id' => $accountId, 'title' => 'Vente lot E2E',
    'expected_revenue' => 500000, 'probability' => 50, 'status' => 'new', 'expected_close_date' => date('Y-m-d', strtotime('+30 days')),
    'subsidiary_id' => $subsidiaryId
]);
if ($oppRes['status'] == 201) echo "✅ Opportunité détectée.\n";
else { echo "❌ Erreur Opportunité: " . ($oppRes['data']['message'] ?? json_encode(['status' => $oppRes['status']])) . "\n"; exit(1); }

// GED (Polymorphisme)
$docRes = makeRequest('POST', "$baseUrl/documents", [
    'title' => 'Brochure E2E', 'file_path' => 'docs/brochure.pdf', 'type' => 'pdf',
    'documentable_id' => $productId, 'documentable_type' => 'App\\Models\\Product',
    'subsidiary_id' => $subsidiaryId
]);
if ($docRes['status'] == 201) echo "✅ Document (Brochure produit) ajouté (GED Polymorphique).\n";
else { echo "❌ Erreur GED: " . ($docRes['data']['message'] ?? json_encode(['status' => $docRes['status']])) . "\n"; exit(1); }

// Frais (Polymorphisme sur Activité)
$expRes = makeRequest('POST', "$baseUrl/expenses", [
    'title' => 'Repas visite E2E', 'type' => 'meal', 'amount' => 15000, 'expense_date' => date('Y-m-d'),
    'expensable_id' => $activityId, 'expensable_type' => 'App\\Models\\Activity',
    'subsidiary_id' => $subsidiaryId
]);
if ($expRes['status'] == 201) {
    echo "✅ Note de frais (Brouillon) créée liée à l'activité.\n";
    $expSubmitRes = makeRequest('POST', "$baseUrl/expenses/{$expRes['data']['id']}/submit");
    if ($expSubmitRes['status'] == 200) echo "✅ Note de frais soumise.\n";
} else { echo "❌ Erreur Expense: " . ($expRes['data']['message'] ?? json_encode(['status' => $expRes['status']])) . "\n"; exit(1); }
echo "\n";

// -------------------------------------------------------------
// 6. SYNCHRONISATION OFFLINE (MOBILE)
// -------------------------------------------------------------
echo "[6] SYNCHRONISATION OFFLINE MOBILE\n";
$syncRes = makeRequest('GET', "$baseUrl/sync/pull?last_sync=2020-01-01T00:00:00Z");
if ($syncRes['status'] == 200 && isset($syncRes['data']['contacts'])) {
    echo "✅ Pull Offline réussi ! Serveur retourne " . count($syncRes['data']['contacts']) . " contacts à jour.\n";
} else { echo "❌ Erreur Sync Pull: " . ($syncRes['data']['message'] ?? json_encode(['status' => $syncRes['status'], 'data' => $syncRes['data'] ?? ''])) . "\n"; exit(1); }

echo "\n===============================================================\n";
echo "🎉 TEST COMPLET RÉUSSI ! 🎉\n";
echo "TOUS LES FLUX DU CRM (Auth -> Config -> Base -> SFA -> Transactions -> Mobile) SONT 100% OPÉRATIONNELS.\n";
echo "===============================================================\n";
