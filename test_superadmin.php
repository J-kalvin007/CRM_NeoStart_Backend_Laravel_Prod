<?php 
require __DIR__.'/vendor/autoload.php'; 
$app = require_once __DIR__.'/bootstrap/app.php'; 
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); 

$user = \App\Models\User::whereHas('roles', fn($q) => $q->where('name', 'superadmin'))->first();
auth()->login($user);

$isGlobal = $user->hasAnyRole(['superadmin', 'admin']) || $user->can('scope:global');
echo "IsGlobal: " . ($isGlobal ? 'true' : 'false') . "\n";

$activities = \App\Models\Activity::count();
echo "Activities with global scope: " . $activities . "\n";

$request = new \Illuminate\Http\Request();
$controller = new \App\Http\Controllers\Api\V1\ActivityController();
$result = $controller->index($request);
echo "Activities returned from controller: " . count($result->resource) . "\n";
