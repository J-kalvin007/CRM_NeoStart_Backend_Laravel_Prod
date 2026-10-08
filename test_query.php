<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$types = \App\Models\AccountType::where(function ($q) { 
    $q->whereHas('countries', function ($subQ) { 
        $subQ->where('countries.id', 'dummy'); 
    })->orWhereDoesntHave('countries'); 
})->active()->get();

echo json_encode($types);
