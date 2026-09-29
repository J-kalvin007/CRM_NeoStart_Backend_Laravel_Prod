<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
var_dump(App\Models\Product::latest()->take(5)->get(['nom', 'code', 'pght', 'prix_cession', 'ppv'])->toArray());
