<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$contact = App\Models\Contact::latest()->first();

if (!$contact) {
    echo "No contacts found.\n";
    exit;
}

$request = Illuminate\Http\Request::create("/api/v1/contacts/{$contact->id}", 'GET');
$controller = new App\Http\Controllers\Api\V1\ContactController();
$response = $controller->show($contact->id);

echo json_encode($response->toArray($request), JSON_PRETTY_PRINT);
