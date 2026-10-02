<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = \Illuminate\Http\Request::create('/api/v1/accounts/01a0e454-07c3-70bf-acae-21e66b17c7fd', 'PUT', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json'
], json_encode([
    'delegue_id' => '9d2a4176-13a8-4c90-b1d5-78e38d011681',
    'user_id' => '9d2a4176-13a8-4c90-b1d5-78e38d011681'
]));

// We need a user to authenticate
$user = \App\Models\User::first();
$app->make('auth')->login($user);

$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Response: " . $response->getContent() . "\n";
