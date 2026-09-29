<?php 
require __DIR__.'/vendor/autoload.php'; 
$app = require_once __DIR__.'/bootstrap/app.php'; 
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); 

// Let's create a new activity for a random user just to be sure it appears
$otherUser = \App\Models\User::whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'))->first();
$activity = \App\Models\Activity::create([
    'user_id' => $otherUser->id,
    'date_activite' => now()->toDateString(),
    'heure_debut' => '09:00',
    'heure_fin' => '09:30',
    'statut' => 'planifiee',
    'activity_type' => 'visite'
]);

echo "Created activity {$activity->id} for user {$otherUser->id}\n";
