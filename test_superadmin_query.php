<?php 
require __DIR__.'/vendor/autoload.php'; 
$app = require_once __DIR__.'/bootstrap/app.php'; 
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); 

$user = \App\Models\User::whereHas('roles', fn($q) => $q->where('name', 'superadmin'))->first();
auth()->login($user);

DB::enableQueryLog();

$request = new \Illuminate\Http\Request();
$controller = new \App\Http\Controllers\Api\V1\ActivityController();
$controller->index($request);

print_r(DB::getQueryLog());
