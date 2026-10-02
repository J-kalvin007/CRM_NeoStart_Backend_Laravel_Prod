<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
$contact = App\Models\Contact::latest()->first();

$request = Illuminate\Http\Request::create("/api/v1/contacts/{$contact->id}", 'GET');
$request->setUserResolver(function () use ($user) {
    return $user;
});

try {
    $response = app()->handle($request);
    echo $response->getContent();
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
