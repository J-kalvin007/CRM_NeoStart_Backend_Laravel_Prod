<?php
// Load Laravel environment
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Str;

echo "=== TEST DU GESTIONNAIRE DE PERMISSIONS (Spatie RBAC) ===\n\n";

// 1. Nettoyage et création des permissions
Permission::firstOrCreate(['name' => 'create-expense']);
Permission::firstOrCreate(['name' => 'approve-expense']);
Permission::firstOrCreate(['name' => 'view-all-accounts']);

$roleDelegate = Role::firstOrCreate(['name' => 'medical-delegate']);
$roleDelegate->syncPermissions(['create-expense', 'view-all-accounts']);
echo "✅ Rôle 'medical-delegate' créé avec les permissions par défaut (create-expense, view-all-accounts).\n\n";

// 2. Création de deux utilisateurs fictifs
$userA = User::firstOrCreate(
    ['email' => 'userA_delegate@crm.local'],
    [
        'name' => 'Agent A (Délégué Standard)',
        'password' => bcrypt('password'),
        'id' => Str::uuid()->toString()
    ]
);

$userB = User::firstOrCreate(
    ['email' => 'userB_special@crm.local'],
    [
        'name' => 'Agent B (Exceptionnel)',
        'password' => bcrypt('password'),
        'id' => Str::uuid()->toString()
    ]
);

// Nettoyage de leurs rôles/permissions pour le test
$userA->syncRoles([]);
$userA->syncPermissions([]);
$userB->syncRoles([]);
$userB->syncPermissions([]);

// 3. SCENARIO A : L'utilisateur a la permission VIA SON RÔLE
echo "-> Affectation du RÔLE 'medical-delegate' à Agent A.\n";
$userA->assignRole('medical-delegate');

if ($userA->hasPermissionTo('create-expense')) {
    echo "✅ SUCCESS : Agent A peut 'create-expense' car il a hérité de la permission via son RÔLE.\n";
} else {
    echo "❌ FAIL : Agent A devrait pouvoir créer une dépense.\n";
}


// 4. SCENARIO B : L'utilisateur a la permission EXCEPTIONNELLEMENT (en direct)
echo "\n-> Agent B n'a AUCUN RÔLE, mais on lui donne la PERMISSION DIRECTE 'create-expense' exceptionnellement.\n";
$userB->givePermissionTo('create-expense');

if ($userB->hasPermissionTo('create-expense')) {
    echo "✅ SUCCESS : Agent B peut 'create-expense' car on lui a accordé cette permission EXCEPTIONNELLEMENT de façon directe, sans passer par un rôle.\n";
} else {
    echo "❌ FAIL : Agent B devrait pouvoir créer une dépense.\n";
}

// 5. Test croisé
if (!$userA->hasPermissionTo('approve-expense')) {
    echo "\n✅ SUCCESS : Agent A NE PEUT PAS 'approve-expense' (Il n'a ni le rôle Manager, ni la permission directe).\n";
}

echo "\n=== CONCLUSION ===\n";
echo "Le système Spatie gère parfaitement les deux cas natifs :\n";
echo "- Permission héritée d'un rôle (userA->hasPermissionTo() == true)\n";
echo "- Permission accordée directement/exceptionnellement (userB->hasPermissionTo() == true)\n";
