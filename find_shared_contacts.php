<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contact;
use Illuminate\Support\Facades\DB;

$duplicates = Contact::select('nom', 'prenom', DB::raw('COUNT(DISTINCT user_id) as delegate_count'))
    ->groupBy('nom', 'prenom')
    ->having('delegate_count', '>', 1)
    ->get();

if ($duplicates->isEmpty()) {
    echo "No contacts shared among delegates found.\n";
} else {
    foreach ($duplicates as $dup) {
        echo "Shared Contact: {$dup->prenom} {$dup->nom} (Delegates: {$dup->delegate_count})\n";
        
        $instances = Contact::with('delegue', 'account')->where('nom', $dup->nom)->where('prenom', $dup->prenom)->get();
        foreach ($instances as $inst) {
            echo "  - ID: {$inst->id} | Delegue: {$inst->delegue->name} | Etablissement: {$inst->account->nom}\n";
        }
        echo "\n";
    }
}
