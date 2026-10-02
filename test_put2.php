<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$acc = App\Models\Account::first();
echo "Before: type=" . $acc->account_type_id . ", delegue=" . $acc->delegue_id . "\n";

$request = Illuminate\Http\Request::create('/api/v1/accounts/' . $acc->id, 'PUT', [
    'account_type_id' => '01a0c97c-52be-70b6-bd86-73e997a747eb',
    'delegue_id' => '01a0c97c-4887-72a1-8323-20cf1191d03e',
    'user_id' => '01a0c97c-4887-72a1-8323-20cf1191d03e'
]);
$request->headers->set('Accept', 'application/json');

$user = App\Models\User::first();
auth()->login($user);

$controller = app()->make(App\Http\Controllers\Api\V1\AccountController::class);
// We need to bypass form request validation for a quick manual call, 
// or let it validate by using the app->handle
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $httpKernel->handle($request);

echo "STATUS: " . $response->getStatusCode() . "\n";
echo "CONTENT: " . $response->getContent() . "\n";

$acc->refresh();
echo "After: type=" . $acc->account_type_id . ", delegue=" . $acc->delegue_id . "\n";
