<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('user_scopes')->truncate();
        DB::table('organizational_units')->truncate();
        Schema::enableForeignKeyConstraints();

        // 1. Migrer les Pays
        $countries = DB::table('countries')->get();
        foreach ($countries as $country) {
            DB::table('organizational_units')->insert([
                'id' => $country->id,
                'name' => $country->name,
                'type' => 'country',
                'parent_id' => null,
                'metadata' => json_encode([
                    'code' => $country->code ?? null, 
                    'currency' => $country->currency ?? null
                ]),
                'created_at' => $country->created_at,
                'updated_at' => $country->updated_at,
            ]);
        }

        // 2. Migrer les Filiales
        $subsidiaries = DB::table('subsidiaries')->get();
        foreach ($subsidiaries as $subsidiary) {
            DB::table('organizational_units')->insert([
                'id' => $subsidiary->id,
                'name' => $subsidiary->name,
                'type' => 'subsidiary',
                'parent_id' => $subsidiary->country_id,
                'metadata' => json_encode([]),
                'created_at' => $subsidiary->created_at,
                'updated_at' => $subsidiary->updated_at,
            ]);
        }

        // 3. Migrer les Régions
        $regions = DB::table('regions')->get();
        foreach ($regions as $region) {
            DB::table('organizational_units')->insert([
                'id' => $region->id,
                'name' => $region->name,
                'type' => 'region',
                'parent_id' => $region->subsidiary_id,
                'metadata' => json_encode([]),
                'created_at' => $region->created_at,
                'updated_at' => $region->updated_at,
            ]);
        }

        // 4. Migrer les Territoires
        $territories = DB::table('territories')->get();
        foreach ($territories as $territory) {
            DB::table('organizational_units')->insert([
                'id' => $territory->id,
                'name' => $territory->name,
                'type' => 'territory',
                'parent_id' => $territory->region_id,
                'metadata' => json_encode([]),
                'created_at' => $territory->created_at,
                'updated_at' => $territory->updated_at,
            ]);
        }

        // 5. Migrer les Zones
        $zones = DB::table('zones')->get();
        foreach ($zones as $zone) {
            DB::table('organizational_units')->insert([
                'id' => $zone->id,
                'name' => $zone->name,
                'type' => 'zone',
                'parent_id' => $zone->territory_id,
                'metadata' => json_encode([]),
                'created_at' => $zone->created_at,
                'updated_at' => $zone->updated_at,
            ]);
        }

        // 6. Migrer les Secteurs
        $sectors = DB::table('sectors')->get();
        foreach ($sectors as $sector) {
            DB::table('organizational_units')->insert([
                'id' => $sector->id,
                'name' => $sector->name,
                'type' => 'sector',
                'parent_id' => $sector->zone_id,
                'metadata' => json_encode([]),
                'created_at' => $sector->created_at,
                'updated_at' => $sector->updated_at,
            ]);
        }

        // 7. Migrer les Équipes
        $teams = DB::table('teams')->get();
        foreach ($teams as $team) {
            DB::table('organizational_units')->insert([
                'id' => $team->id,
                'name' => $team->name,
                'type' => 'team',
                'parent_id' => $team->sector_id,
                'metadata' => json_encode(['manager_id' => $team->manager_id]), // On garde l'info du manager d'équipe ici
                'created_at' => $team->created_at,
                'updated_at' => $team->updated_at,
            ]);
        }

        // 8. Migrer les Utilisateurs vers user_scopes (en utilisant le niveau d'affectation le plus précis disponible)
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            $assignedUnitId = null;

            // Logique de cascade inversée (on cherche le niveau le plus précis auquel l'utilisateur est rattaché)
            if (!empty($user->team_id)) {
                $assignedUnitId = $user->team_id;
            } elseif (!empty($user->sector_id)) {
                $assignedUnitId = $user->sector_id;
            } elseif (!empty($user->zone_id)) {
                $assignedUnitId = $user->zone_id;
            } elseif (!empty($user->territory_id)) {
                $assignedUnitId = $user->territory_id;
            } elseif (!empty($user->region_id)) {
                $assignedUnitId = $user->region_id;
            } elseif (!empty($user->subsidiary_id)) {
                $assignedUnitId = $user->subsidiary_id;
            } elseif (!empty($user->country_id)) {
                $assignedUnitId = $user->country_id;
            }

            if ($assignedUnitId) {
                DB::table('user_scopes')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'organizational_unit_id' => $assignedUnitId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('organizational_units')->truncate();
        DB::table('user_scopes')->truncate();
    }
};
