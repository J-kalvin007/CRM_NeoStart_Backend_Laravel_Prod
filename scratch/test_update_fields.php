<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$contact = App\Models\Contact::latest()->first();
if (!$contact) {
    echo "No contact found.\n";
    exit;
}

$payload = [
    'nom' => 'Test',
    'prenom' => 'Update',
    'account_id' => App\Models\Account::first()->id ?? null,
    'telephone' => '11223344',
    'potentiel' => 'fort',
    'meta' => [
        'is_kol' => true,
        'rpps' => '99999'
    ]
];

$request = Illuminate\Http\Request::create("/api/v1/contacts/{$contact->id}", 'PUT', $payload);
$request->headers->set('Accept', 'application/json');
$controller = app(App\Http\Controllers\Api\V1\ContactController::class);

try {
    $response = $controller->update(
        app(App\Http\Requests\UpdateContactRequest::class)->createFrom($request),
        $contact->id,
        app(App\Services\DynamicValidationService::class),
        app(App\Services\DuplicateDetectionService::class)
    );
    echo json_encode($response->toArray(request()), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    if (method_exists($e, 'errors')) {
        echo json_encode($e->errors(), JSON_PRETTY_PRINT);
    }
}
