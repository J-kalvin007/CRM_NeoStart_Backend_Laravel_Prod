<?php 
require __DIR__.'/vendor/autoload.php'; 
$app = require_once __DIR__.'/bootstrap/app.php'; 
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); 

$count1 = \App\Models\Activity::count();
$count2 = \App\Models\Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->count();
$count3 = \App\Models\Activity::withoutGlobalScopes()->count();
echo "Default: $count1\n";
echo "Without PerimeterScope: $count2\n";
echo "Without all: $count3\n";
