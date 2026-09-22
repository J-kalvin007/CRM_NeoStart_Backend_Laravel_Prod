<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
if (!$user) {
    echo "No super admin found.\n";
    exit;
}

$contact = App\Models\Contact::latest()->first();
$account = App\Models\Account::first();
$type = App\Models\ContactType::first();

echo "Testing update for Contact ID: {$contact->id}\n";
echo "Account ID: {$account->id}\n";
echo "Type ID: {$type->id}\n";

$request = Illuminate\Http\Request::create("/api/v1/contacts/{$contact->id}", 'PUT', [
    'nom' => 'TestUpdate',
    'prenom' => 'Testing',
    'account_id' => $account->id,
    'contact_type_id' => $type->id,
    'telephone' => '99887766',
    'potentiel' => 'fort',
    'meta' => [
        'is_kol' => true,
        'specialite' => 'Chirurgie'
    ]
]);
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Since FormRequest needs to be properly bound to container, we use a test case approach or we use a controller directly
// Wait, calling controller method with FormRequest requires app()->make()
try {
    $response = app()->handle($request);
    echo $response->getContent();
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
