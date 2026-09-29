<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$accountId = App\Models\Account::first()->id;

$request = Illuminate\Http\Request::create('/api/v1/accounts/' . $accountId, 'PUT', [
    'account_type_id' => '01a0c97c-52be-70b6-bd86-73e997a747eb',
    'delegue_id' => '01a0c97c-4887-72a1-8323-20cf1191d03e',
    'user_id' => '01a0c97c-4887-72a1-8323-20cf1191d03e'
]);
$request->headers->set('Accept', 'application/json');

// By-pass auth for this manual test by acting as first user
$user = App\Models\User::first();
auth()->login($user);

$response = $kernel->handle($request);

echo "STATUS: " . $response->getStatusCode() . "\n";
echo "CONTENT: " . $response->getContent() . "\n";
