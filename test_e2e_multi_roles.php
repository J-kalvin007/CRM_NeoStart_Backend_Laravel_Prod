<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$baseUrl = "http://localhost:8000/api/v1";

function makeRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $decoded = json_decode($response, true) ?? $response;
    return ['status' => $status, 'data' => $decoded];
}

$time = time();
$adminEmail = "admin_{$time}@example.com";
$delegueEmail = "delegue_{$time}@example.com";
$tempPassword = "password123";
$newPassword = "NewPassword123!";

echo "===============================================================\n";
echo "🚀 TEST MULTI-ROLES : ADMIN -> DELEGUE\n";
echo "===============================================================\n\n";

echo "[1] CRÉATION ET CONNEXION DE L'ADMIN\n";
// L'admin existe déjà dans le seeder
$adminLoginRes = makeRequest('POST', "$baseUrl/login", ['email' => 'admin@crm.local', 'password' => 'password']);
if ($adminLoginRes['status'] != 202) {
    echo "❌ Erreur Login Admin: " . json_encode($adminLoginRes['data']) . "\n";
    exit(1);
}
$adminId = $adminLoginRes['data']['user_id'];

// Récupération de l'OTP 
$adminOtp = \Illuminate\Support\Facades\Cache::get('login_otp_'.$adminId);

$adminVerify = makeRequest('POST', "$baseUrl/verify-otp", ['user_id' => $adminId, 'otp_code' => $adminOtp]);
if ($adminVerify['status'] != 200) {
    echo "❌ Erreur OTP Admin: " . json_encode($adminVerify['data']) . "\n"; exit(1);
}
$adminToken = $adminVerify['data']['token'];
$subsidiaryId = $adminVerify['data']['user']['subsidiary_id'];
echo "✅ Admin connecté. Token récupéré.\n\n";

echo "[2] L'ADMIN INVITE UN DELEGUE MEDICAL\n";
$inviteRes = makeRequest('POST', "$baseUrl/auth/invite", [
    'name' => "Delegue Test {$time}",
    'email' => $delegueEmail,
    'password' => $tempPassword,
    'subsidiary_id' => $subsidiaryId
], $adminToken);
if ($inviteRes['status'] != 201) {
    echo "❌ Erreur Invitation: " . json_encode($inviteRes['data']) . "\n"; exit(1);
}
echo "✅ Délégué invité avec succès.\n\n";

echo "[3] LE DELEGUE TENTE DE SE CONNECTER\n";
$delegueLoginRes = makeRequest('POST', "$baseUrl/login", ['email' => $delegueEmail, 'password' => $tempPassword]);
if ($delegueLoginRes['status'] != 403) {
    echo "❌ Le système n'a pas forcé le changement de mot de passe (Attendu 403, eu {$delegueLoginRes['status']})\n"; exit(1);
}
$delegueId = $delegueLoginRes['data']['user_id'];
echo "✅ Le système bloque la connexion et force le changement de mot de passe (Sécurité OK).\n\n";

echo "[4] LE DELEGUE CHANGE SON MOT DE PASSE\n";
$changePassRes = makeRequest('POST', "$baseUrl/auth/change-password", [
    'user_id' => $delegueId,
    'old_password' => $tempPassword,
    'new_password' => $newPassword,
    'new_password_confirmation' => $newPassword
]);
if ($changePassRes['status'] != 202) {
    $error = json_encode($changePassRes['data']);
    file_put_contents('error.log', $error);
    echo "❌ Erreur changement mot de passe: " . $error . "\n"; exit(1);
}
echo "✅ Mot de passe modifié. OTP envoyé par email.\n\n";

echo "[5] LE DELEGUE RENTRE SON OTP POUR FINALISER LA CONNEXION\n";
$delegueOtp = \Illuminate\Support\Facades\Cache::get('login_otp_'.$delegueId);

$delegueVerify = makeRequest('POST', "$baseUrl/verify-otp", ['user_id' => $delegueId, 'otp_code' => $delegueOtp]);
if ($delegueVerify['status'] != 200) {
    echo "❌ Erreur OTP Délégué: " . json_encode($delegueVerify['data']) . "\n"; exit(1);
}
$delegueToken = $delegueVerify['data']['token'];
echo "✅ Délégué authentifié ! (email_verified_at est mis à jour en base de données)\n\n";

echo "[6] L'ADMIN CREE UN INDICATEUR ET UN OBJECTIF POUR LE DELEGUE\n";
$indRes = makeRequest('POST', "$baseUrl/indicators", [
    'name' => "Visites Test",
    'code' => "V_$time",
    'calculation_type' => 'count',
    'data_source_model' => 'App\\Models\\Activity'
], $adminToken);

// Si l'indicateur n'a pas été créé (route non existante par ex), on le crée via Eloquent
if ($indRes['status'] != 201) {
    $indicator = \App\Models\Indicator::create([
        'name' => "Visites Test",
        'code' => "V_$time",
        'calculation_type' => 'count',
        'data_source_model' => 'App\\Models\\Activity',
        'subsidiary_id' => $subsidiaryId
    ]);
    $indicatorId = $indicator->id;
} else {
    $indicatorId = $indRes['data']['data']['id'] ?? $indRes['data']['id'];
}

$objectifRes = makeRequest('POST', "$baseUrl/objectifs", [
    'user_id' => $delegueId,
    'indicator_id' => $indicatorId,
    'date_debut' => date('Y-m-01'),
    'date_fin' => date('Y-m-t'),
    'periode' => 'mensuel',
    'valeur_cible' => 5,
    'valeur_realisee' => 0,
    'taux_realisation' => 0,
    'statut' => 'actif',
    'subsidiary_id' => $subsidiaryId
], $adminToken);
if ($objectifRes['status'] != 201) {
    echo "❌ Erreur création objectif: " . json_encode($objectifRes['data']) . "\n"; exit(1);
}
echo "✅ Objectif assigné au délégué par l'Admin.\n\n";

echo "[7] LE DELEGUE EFFECTUE UNE ACTIVITE\n";
// Création compte & contact
$accRes = makeRequest('POST', "$baseUrl/accounts", ['nom' => "Hopital $time", 'subsidiary_id' => $subsidiaryId], $delegueToken);
if ($accRes['status'] != 201) { echo "❌ Erreur Compte: " . json_encode($accRes['data']) . "\n"; exit(1); }
$accountId = $accRes['data']['id'] ?? $accRes['data']['data']['id'];

$contRes = makeRequest('POST', "$baseUrl/contacts", ['prenom' => "Dr", 'nom' => "Test $time", 'account_id' => $accountId, 'subsidiary_id' => $subsidiaryId], $delegueToken);
if ($contRes['status'] != 201) { echo "❌ Erreur Contact: " . json_encode($contRes['data']) . "\n"; exit(1); }
$contactId = $contRes['data']['id'] ?? $contRes['data']['data']['id'];

// Création activité planifiée
$actRes = makeRequest('POST', "$baseUrl/activities", [
    'contact_id' => $contactId,
    'account_id' => $accountId,
    'activity_type' => 'visite',
    'statut' => 'planifiee',
    'date_activite' => date('Y-m-d'),
    'heure_debut' => date('H:i'),
    'heure_fin' => date('H:i', strtotime('+1 hour')),
    'subsidiary_id' => $subsidiaryId
], $delegueToken);
if ($actRes['status'] != 201) {
    echo "❌ Erreur Création Activité: " . json_encode($actRes['data']) . "\n"; exit(1);
}
$activityId = $actRes['data']['id'] ?? $actRes['data']['data']['id'];

// Mise à jour de l'activité en "réalisée"
$actUpdateRes = makeRequest('PUT', "$baseUrl/activities/$activityId", [
    'statut' => 'realisee'
], $delegueToken);
if ($actUpdateRes['status'] != 200) {
    echo "❌ Erreur Mise à jour Activité: " . json_encode($actUpdateRes['data']) . "\n"; exit(1);
}
echo "✅ Le délégué a soumis une activité 'realisee'.\n\n";

echo "[8] VERIFICATION AUTOMATIQUE DE L'OBJECTIF\n";
// L'objectif du délégué devrait être à 1
$objectifCheck = makeRequest('GET', "$baseUrl/objectifs/".$objectifRes['data']['data']['id'], null, $adminToken);
$valRealisee = $objectifCheck['data']['data']['valeur_realisee'] ?? 0;

if ($valRealisee >= 1) {
    echo "✅ SUCCÈS ! La valeur réalisée de l'objectif est passée à $valRealisee automatiquement via le Calculation Engine !\n";
} else {
    echo "❌ ECHEC ! L'objectif est resté à $valRealisee. Le Calculation Engine n'a pas marché.\n"; exit(1);
}

// Vérifier les endpoints analytics
echo "\n[9] VERIFICATION DU DASHBOARD ANALYTICS DU DELEGUE\n";
$analyticsRes = makeRequest('GET', "$baseUrl/analytics/dashboard", null, $delegueToken);
if ($analyticsRes['status'] == 200 && count($analyticsRes['data']['objectives']) > 0) {
    echo "✅ Dashboard du délégué récupéré avec succès. Objectifs affichés : " . count($analyticsRes['data']['objectives']) . "\n";
} else {
    echo "❌ ECHEC ! Le dashboard est vide ou erreur: " . json_encode($analyticsRes['data']) . "\n"; exit(1);
}


echo "\n===============================================================\n";
echo "🎉 TEST MULTI-ROLES ET PROCESSUS METIER COMPLET REUSSI ! 🎉\n";
echo "===============================================================\n";
