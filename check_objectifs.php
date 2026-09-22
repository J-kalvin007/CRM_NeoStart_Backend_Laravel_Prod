<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$objectifs = \App\Models\Objectif::count();
echo "Total objectifs: " . $objectifs . "\n";
