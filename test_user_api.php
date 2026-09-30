<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/api/v1/users/01a0c97c-4887-72a1-8323-20cf1191d03e', 'GET');
$response = app()->handle($request);
echo $response->getContent();
