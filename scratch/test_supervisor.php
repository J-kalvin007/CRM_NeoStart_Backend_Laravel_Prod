<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
$app = app();
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Find a supervisor explicitly by querying a known ID or finding one that has 'superviseur' role
$supervisor = App\Models\User::whereHas('roles', function($q) {
    $q->whereIn('name', ['superviseur', 'supervisor', 'SUPERVISEUR']);
})->first();

if (!$supervisor) {
    echo "No supervisor found\n";
    exit;
}
echo "Supervisor: " . $supervisor->name . " (ID: " . $supervisor->id . ")\n";

$subs = $supervisor->subordinates()->pluck('id')->toArray();
echo "Subordinates: " . json_encode($subs) . "\n";

$userIds = array_merge([$supervisor->id], $subs);

$accountsCount = App\Models\Account::whereIn('user_id', $userIds)
    ->orWhereIn('delegue_id', $userIds)
    ->orWhereIn('created_by', $userIds)
    ->orWhereIn('id', function ($sub) use ($userIds) {
        $sub->select('account_id')
            ->from('contacts')
            ->whereIn('user_id', $userIds)
            ->whereNotNull('account_id');
    })
    ->count();

echo "Accounts count for subs: " . $accountsCount . "\n";
