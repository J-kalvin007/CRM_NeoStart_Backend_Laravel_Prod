<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$contact = \App\Models\Contact::with(['account', 'contactType'])->first();
echo json_encode($contact, JSON_PRETTY_PRINT);
