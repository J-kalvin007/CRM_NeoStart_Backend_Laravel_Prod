<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::whereHas('roles', fn($q)=>$q->where('name', 'superviseur'))->first();

$count = App\Models\Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
    ->where(function ($subQ) use ($user) {
        $subQ->whereHas('delegue', function ($delegueQuery) use ($user) {
            $delegueQuery->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
                ->where('manager_id', $user->id);
        });
    })->count();
    
echo "Count activities via whereHas(delegue, manager_id = user->id) = $count\n";

$count2 = App\Models\Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
    ->whereIn('user_id', clone $user->teamMemberIds())
    ->count();

echo "Count activities via whereIn(user_id, teamMemberIds) = $count2\n";
