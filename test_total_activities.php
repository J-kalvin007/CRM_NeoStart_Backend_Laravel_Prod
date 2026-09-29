<?php 
require __DIR__.'/vendor/autoload.php'; 
$app = require_once __DIR__.'/bootstrap/app.php'; 
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); 

$count = \App\Models\Activity::withoutGlobalScopes()->count();
echo "Total activities: " . $count . "\n";
