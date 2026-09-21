<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$account = \App\Models\Account::withTrashed()->find('01a0c1ff-6f91-72e2-bfcf-7275dad71bf5');
echo json_encode($account);
