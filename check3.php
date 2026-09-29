<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$total = App\Models\Contact::withoutGlobalScopes()->count();
$softDeleted = App\Models\Contact::withoutGlobalScopes()->withTrashed()->onlyTrashed()->count();
$valide = App\Models\Contact::withoutGlobalScopes()->where('statut_validation', 'valide')->count();
$enAttente = App\Models\Contact::withoutGlobalScopes()->where('statut_validation', 'en_attente')->count();
$actif = App\Models\Contact::withoutGlobalScopes()->where('statut', 'actif')->count();
$enAttente2 = App\Models\Contact::withoutGlobalScopes()->where('statut', 'en_attente')->count();

echo "Total contacts: $total\n";
echo "Soft deleted: $softDeleted\n";
echo "statut_validation=valide: $valide\n";
echo "statut_validation=en_attente: $enAttente\n";
echo "statut=actif: $actif\n";
echo "statut=en_attente: $enAttente2\n";

// Voir les user_ids distincts et combien de contacts chacun a
$byUser = App\Models\Contact::withoutGlobalScopes()
    ->select('user_id', \Illuminate\Support\Facades\DB::raw('count(*) as cnt'))
    ->groupBy('user_id')
    ->get();

echo "\nContacts par user_id:\n";
foreach ($byUser as $row) {
    $user = App\Models\User::find($row->user_id);
    echo "  " . ($user?->name ?? 'null') . " : {$row->cnt} contacts\n";
}

// Vérifier les UserScopes
echo "\nUserScopes actifs:\n";
$scopes = App\Models\UserScope::with('user', 'organizationalUnit')
    ->where('is_active', true)
    ->get();
foreach ($scopes as $scope) {
    echo "  User: {$scope->user?->name} → Unit: {$scope->organizationalUnit?->name}\n";
}
