<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'like', '%super%')->first() ?? \App\Models\User::first();
echo "User: " . $user->email . "\n";
echo "Roles: " . json_encode($user->roles->pluck('name')) . "\n";
