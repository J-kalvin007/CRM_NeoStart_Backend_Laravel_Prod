<?php

require __DIR__ . '/../CRM-backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../CRM-backend/bootstrap/app.php';

// Initialiser l'application Laravel
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Region;
use App\Models\Territory;
use App\Models\Zone;
use App\Models\Sector;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

echo "=========================================================\n";
echo "🛠 LANCEMENT DU TEST DE LA HIERARCHIE ET DE LA VISIBILITÉ\n";
echo "=========================================================\n\n";

try {
    DB::beginTransaction();

    echo "1. Nettoyage et préparation de la base pour le test...\n";
    // Nettoyer uniquement les données de ce test s'ils existent déjà (basé sur le nom)
    $testPrefix = "TEST_HIERARCHY_";

    echo "2. Création de l'arbre géographique...\n";
    
    // Création de la Région
    $region = Region::create(['name' => $testPrefix . 'Region_Nord']);
    
    // Création du Territoire lié à la Région
    $territory = Territory::create(['name' => $testPrefix . 'Territoire_Lille', 'region_id' => $region->id]);

    // Création de la Zone liée au Territoire
    $zone = Zone::create(['name' => $testPrefix . 'Zone_Lille_Centre', 'territory_id' => $territory->id]);

    // Création de deux Secteurs liés à la Zone
    $sectorA = Sector::create(['name' => $testPrefix . 'Secteur_A_Mairie', 'zone_id' => $zone->id]);
    $sectorB = Sector::create(['name' => $testPrefix . 'Secteur_B_Gare', 'zone_id' => $zone->id]);

    echo "   [Vérification de l'Arbre] \n";
    echo "   -> Région: " . $region->name . " (ID Unit: " . $region->organizational_unit_id . ")\n";
    echo "   -> Territoire: " . $territory->name . " (ID Unit: " . $territory->organizational_unit_id . ")\n";
    echo "   -> Zone: " . $zone->name . " (ID Unit: " . $zone->organizational_unit_id . ")\n";
    echo "   -> Secteur A: " . $sectorA->name . " (ID Unit: " . $sectorA->organizational_unit_id . ")\n";
    echo "   -> Secteur B: " . $sectorB->name . " (ID Unit: " . $sectorB->organizational_unit_id . ")\n\n";


    echo "3. Création des Utilisateurs...\n";
    $dirReg = User::factory()->create(['name' => 'Test_Directeur_Nord', 'email' => 'dir_nord@test.com']);
    $dirReg->scopes()->attach($region->organizational_unit_id); // Assigne au sommet de l'arbre
    
    $superviseur = User::factory()->create(['name' => 'Test_Sup_Lille', 'email' => 'sup_lille@test.com']);
    $superviseur->scopes()->attach($zone->organizational_unit_id); // Assigne à la zone

    $delegueA = User::factory()->create(['name' => 'Test_Delegue_A', 'email' => 'del_a@test.com']);
    $delegueA->scopes()->attach($sectorA->organizational_unit_id); // Assigne au Secteur A

    $delegueB = User::factory()->create(['name' => 'Test_Delegue_B', 'email' => 'del_b@test.com']);
    $delegueB->scopes()->attach($sectorB->organizational_unit_id); // Assigne au Secteur B


    echo "4. Création des Médecins (Contacts)...\n";
    // 2 médecins pour le délégué A
    Contact::factory()->count(2)->create(['user_id' => $delegueA->id, 'nom' => 'Medecin_Secteur_A']);
    // 3 médecins pour le délégué B
    Contact::factory()->count(3)->create(['user_id' => $delegueB->id, 'nom' => 'Medecin_Secteur_B']);


    echo "\n5. SIMULATION DE CONNEXION ET VÉRIFICATION DES VISIBILITÉS\n";
    echo "---------------------------------------------------------\n";

    // TEST 1 : Délégué A
    Auth::login($delegueA);
    $contactsA = Contact::where('nom', 'LIKE', 'Medecin_Secteur_%')->count();
    echo "-> Connexion du Délégué A (Secteur A).\n";
    echo "   Contacts attendus : 2. Contacts trouvés : " . $contactsA . " \n";
    if ($contactsA == 2) { echo "   ✅ SUCCÈS : Le délégué A ne voit que ses médecins.\n"; } 
    else { echo "   ❌ ÉCHEC\n"; }

    // TEST 2 : Délégué B
    Auth::login($delegueB);
    $contactsB = Contact::where('nom', 'LIKE', 'Medecin_Secteur_%')->count();
    echo "\n-> Connexion du Délégué B (Secteur B).\n";
    echo "   Contacts attendus : 3. Contacts trouvés : " . $contactsB . " \n";
    if ($contactsB == 3) { echo "   ✅ SUCCÈS : Le délégué B ne voit que ses médecins.\n"; } 
    else { echo "   ❌ ÉCHEC\n"; }

    // TEST 3 : Superviseur (Zone)
    Auth::login($superviseur);
    $contactsSup = Contact::where('nom', 'LIKE', 'Medecin_Secteur_%')->count();
    echo "\n-> Connexion du Superviseur (Zone Lille Centre).\n";
    echo "   Contacts attendus : 5 (2 du Secteur A + 3 du Secteur B). Contacts trouvés : " . $contactsSup . " \n";
    if ($contactsSup == 5) { echo "   ✅ SUCCÈS : La cascade fonctionne de Secteur à Zone.\n"; } 
    else { echo "   ❌ ÉCHEC\n"; }

    // TEST 4 : Directeur Régional (Région)
    Auth::login($dirReg);
    $contactsDir = Contact::where('nom', 'LIKE', 'Medecin_Secteur_%')->count();
    echo "\n-> Connexion du Directeur Régional (Région Nord).\n";
    echo "   Contacts attendus : 5. Contacts trouvés : " . $contactsDir . " \n";
    if ($contactsDir == 5) { echo "   ✅ SUCCÈS : La cascade fonctionne sur l'arbre complet (de la Région aux Secteurs).\n"; } 
    else { echo "   ❌ ÉCHEC\n"; }

} catch (\Exception $e) {
    echo "\n❌ ERREUR LORS DU TEST : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
} finally {
    // Annuler toutes les insertions pour garder la base de données propre
    DB::rollBack();
    echo "\n=========================================================\n";
    echo "♻️ Base de données nettoyée (Rollback de la transaction).\n";
    echo "=========================================================\n";
}

