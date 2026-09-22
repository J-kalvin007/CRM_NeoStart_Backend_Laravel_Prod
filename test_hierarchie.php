<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Country;
use App\Models\Subsidiary;
use App\Models\Region;
use App\Models\Territory;
use App\Models\Zone;
use App\Models\Sector;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

echo "=========================================================\n";
echo "🛠 LANCEMENT DU TEST MASSIF DE LA HIERARCHIE (LOAD TEST)\n";
echo "=========================================================\n\n";

try {
    DB::beginTransaction();

    $testPrefix = "TEST_TREE_";
    echo "1. Création de multiples Pays avec leurs hiérarchies...\n";
    
    // ==========================================
    // PAYS 1 : FRANCE (Hiérarchie Complète)
    // ==========================================
    $countryFr = Country::create(['name' => 'Pays_France', 'iso_code' => 'FR', 'code' => 'FR_C_' . uniqid()]);
    $ouFr = \App\Models\OrganizationalUnit::create(['name' => 'Pays_France', 'type' => 'country']);

    $subFr = Subsidiary::create(['name' => 'Filiale_France_Pharma', 'code' => 'FR_P_' . uniqid(), 'country_id' => $countryFr->id]);
    $ouSubFr = \App\Models\OrganizationalUnit::create(['name' => 'Filiale_France_Pharma', 'type' => 'subsidiary', 'parent_id' => $ouFr->id]);
    $subFr->updateQuietly(['organizational_unit_id' => $ouSubFr->id]);
    
    $regNord = Region::create(['name' => 'Region_Nord', 'subsidiary_id' => $subFr->id]);
    $ouRegion = \App\Models\OrganizationalUnit::find($regNord->organizational_unit_id);
    if ($ouRegion) $ouRegion->update(['parent_id' => $ouSubFr->id]);

    $terLille = Territory::create(['name' => 'Territoire_Lille', 'region_id' => $regNord->id]);
    $zonLilleC = Zone::create(['name' => 'Zone_Lille_Centre', 'code' => 'ZL_' . uniqid(), 'territory_id' => $terLille->id]);
    $secMairie = Sector::create(['name' => 'Secteur_Mairie', 'code' => 'SM_' . uniqid(), 'zone_id' => $zonLilleC->id]);
    $secGare = Sector::create(['name' => 'Secteur_Gare', 'code' => 'SG_' . uniqid(), 'zone_id' => $zonLilleC->id]);

    // ==========================================
    // PAYS 2 : LUXEMBOURG (Sauts de niveaux)
    // ==========================================
    $countryLux = Country::create(['name' => 'Pays_Luxembourg', 'iso_code' => 'LU', 'code' => 'LU_C_' . uniqid()]);
    $ouLux = \App\Models\OrganizationalUnit::create(['name' => 'Pays_Luxembourg', 'type' => 'country']);

    $subLux = Subsidiary::create(['name' => 'Filiale_Luxembourg_Health', 'code' => 'LU_H_' . uniqid(), 'country_id' => $countryLux->id]);
    $ouSubLux = \App\Models\OrganizationalUnit::create(['name' => 'Filiale_Luxembourg_Health', 'type' => 'subsidiary', 'parent_id' => $ouLux->id]);
    $subLux->updateQuietly(['organizational_unit_id' => $ouSubLux->id]);
    
    // Le Luxembourg est petit : Les secteurs sont rattachés directement à la Filiale !
    $secLux1 = \App\Models\OrganizationalUnit::create([
        'name' => 'Secteur_Lux_Centre (Direct Filiale)',
        'type' => 'sector',
        'parent_id' => $ouSubLux->id,
    ]);

    $secLux2 = \App\Models\OrganizationalUnit::create([
        'name' => 'Secteur_Lux_Nord (Direct Filiale)',
        'type' => 'sector',
        'parent_id' => $ouSubLux->id,
    ]);

    // ==========================================
    // PAYS 3 : SUISSE (Seulement Régions et Secteurs)
    // ==========================================
    $countryCh = Country::create(['name' => 'Pays_Suisse', 'iso_code' => 'CH', 'code' => 'CH_C_' . uniqid()]);
    $ouCh = \App\Models\OrganizationalUnit::create(['name' => 'Pays_Suisse', 'type' => 'country']);

    $subCh = Subsidiary::create(['name' => 'Filiale_Suisse', 'code' => 'CH_S_' . uniqid(), 'country_id' => $countryCh->id]);
    $ouSubCh = \App\Models\OrganizationalUnit::create(['name' => 'Filiale_Suisse', 'type' => 'subsidiary', 'parent_id' => $ouCh->id]);
    $subCh->updateQuietly(['organizational_unit_id' => $ouSubCh->id]);

    $regGeneve = Region::create(['name' => 'Region_Geneve', 'subsidiary_id' => $subCh->id]);
    $ouRegGeneve = \App\Models\OrganizationalUnit::find($regGeneve->organizational_unit_id);
    if ($ouRegGeneve) $ouRegGeneve->update(['parent_id' => $ouSubCh->id]);

    // Rattachons un secteur directement à la Région Genève (Saut du Territoire et Zone)
    $secGeneve = \App\Models\OrganizationalUnit::create([
        'name' => 'Secteur_Geneve_Ville (Direct Région)',
        'type' => 'sector',
        'parent_id' => $ouRegGeneve->id,
    ]);

    echo "\n=========================================================\n";
    echo "🌳 AFFICHAGE DE L'ARBRE HIÉRARCHIQUE EXACT (Comme dans la BDD)\n";
    echo "=========================================================\n\n";

    // Fonction récursive pour afficher l'arbre
    function printTree($unit, $level = 0) {
        $indent = str_repeat("    ", $level);
        $prefix = $level == 0 ? "🏢 " : " └─ ";
        
        // Formatage du nom et du type
        $typeLabel = strtoupper($unit->type ?? 'INCONNU');
        echo $indent . $prefix . "[" . $typeLabel . "] " . $unit->name . "\n";

        // Récupérer les enfants (directs)
        $children = \App\Models\OrganizationalUnit::where('parent_id', $unit->id)->get();
        foreach ($children as $child) {
            printTree($child, $level + 1);
        }
    }

    // On récupère les racines (les filiales / pays créés qui n'ont pas de parent dans cet exemple simpliste)
    // Dans notre logique actuelle, l'Observer ne crée l'arbre qu'à partir des Régions.
    // Corrigeons cela en récupérant les unités racines générées par le test.
    $rootNodes = \App\Models\OrganizationalUnit::whereNull('parent_id')->get();
    
    foreach ($rootNodes as $root) {
        printTree($root);
        echo "\n";
    }

} catch (\Exception $e) {
    echo "\n❌ ERREUR LORS DU TEST : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
} finally {
    DB::rollBack();
}
