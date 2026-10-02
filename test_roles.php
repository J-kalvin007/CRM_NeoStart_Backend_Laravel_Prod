<?php 
require __DIR__.'/vendor/autoload.php'; 
$app = require_once __DIR__.'/bootstrap/app.php'; 
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); 

$supervisor = \App\Models\User::whereHas('roles', fn($q) => $q->where('name', 'superviseur'))->first();
auth()->login($supervisor);

$deletedTeamActivities = \App\Models\Activity::withoutGlobalScopes()->whereHas('user', fn($q) => $q->where('manager_id', $supervisor->id))->whereNotNull('deleted_at')->count();
echo "Deleted team activities: " . $deletedTeamActivities . "\n";
