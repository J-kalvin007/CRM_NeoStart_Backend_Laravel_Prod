<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preflight for databases where the older cleanup migrations were skipped.
        if (!Schema::hasTable('legacy_migration_archive')) {
            Schema::create('legacy_migration_archive', function (Blueprint $t) {
                $t->id();
                $t->string('source_table', 100);
                $t->string('source_key', 100);
                $t->longText('payload');
                $t->timestamp('archived_at');
                $t->unique(['source_table', 'source_key']);
            });
        }
        $retired = ['teams', 'sectors', 'zones', 'territories', 'subsidiaries', 'regions',
            'organizational_unit_product', 'user_scopes', 'organizational_units',
            'hierarchy_level_definitions', 'organization_configs'];
        $legacy = ['team_id', 'sector_id', 'zone_id', 'territory_id', 'subsidiary_id', 'region_id', 'organizational_unit_id'];
        foreach (Schema::getTableListing(Schema::getCurrentSchemaListing(), schemaQualified: false) as $table) {
            if ($table === 'legacy_migration_archive') continue;
            $columns = Schema::getColumnListing($table);
            if (!in_array('id', $columns, true)) continue;
            $fields = in_array($table, $retired, true) ? $columns : array_values(array_intersect($columns, $legacy));
            if ($table === 'products') {
                $fields = array_unique(array_merge($fields, array_intersect($columns,
                    ['country_id', 'pght', 'prix_cession', 'ppv', 'devise', 'stock_actuel', 'stock_alerte'])));
            }
            if (!$fields) continue;
            DB::table($table)->select(array_unique(array_merge(['id'], $fields)))->orderBy('id')->chunk(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table('legacy_migration_archive')->insertOrIgnore([
                        'source_table' => $table, 'source_key' => (string) $row->id,
                        'payload' => json_encode($row, JSON_THROW_ON_ERROR), 'archived_at' => now(),
                    ]);
                }
            });
        }
        foreach (['users', 'accounts', 'documents', 'objectifs', 'products'] as $table) {
            if (!Schema::hasTable($table)) continue;
            if ($table === 'products' && Schema::hasTable('country_product')) continue;
            if (!Schema::hasColumn($table, 'country_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete());
            }
            if (Schema::hasColumn($table, 'subsidiary_id') && Schema::hasTable('subsidiaries')) {
                foreach (DB::table('subsidiaries')->get() as $subsidiary) {
                    DB::table($table)->whereNull('country_id')->where('subsidiary_id', $subsidiary->id)->update(['country_id' => $subsidiary->country_id]);
                }
            }
        }
        if (Schema::hasColumn('users', 'team_id') && Schema::hasTable('teams')) {
            foreach (DB::table('teams')->get() as $team) {
                if ($team->manager_id) {
                    DB::table('users')->where('team_id', $team->id)->whereNull('manager_id')
                        ->where('id', '!=', $team->manager_id)->update(['manager_id' => $team->manager_id]);
                }
                if (!empty($team->country_id)) {
                    DB::table('users')->where('team_id', $team->id)->whereNull('country_id')->update(['country_id' => $team->country_id]);
                }
            }
        }
        // Legacy global prices have an unambiguous destination in a single-country database.
        if (!Schema::hasTable('country_product') && DB::table('countries')->count() === 1 && Schema::hasColumn('products', 'country_id')) {
            DB::table('products')->whereNull('country_id')->update(['country_id' => DB::table('countries')->value('id')]);
        }
    }

    public function down(): void
    {
        // Retain recovery data across rollbacks.
    }
};
