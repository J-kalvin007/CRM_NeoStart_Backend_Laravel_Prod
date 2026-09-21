<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$contact = \App\Models\Contact::withTrashed()->with(['account' => fn($q) => $q->withTrashed(), 'contactType'])->find('01a0c1ff-6fd4-7068-9f36-aca0c01d6a3d');
echo json_encode($contact, JSON_PRETTY_PRINT);
