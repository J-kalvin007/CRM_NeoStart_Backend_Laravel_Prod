<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$a = App\Models\Account::find('01a0f1fe-3ae7-72a3-844b-739f2d9d1367');
if ($a) {
    print_r($a->toArray());
} else {
    echo "Account not found";
}
