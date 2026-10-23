<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove legacy geography tables (teams, sectors, zones, territories, subsidiaries, regions)
     * Keep only countries and organizational_units
     */
    public function up(): void
    {
        // Blueprint operations execute after the callback: inspect actual FK names.
        $retired = ['teams', 'sectors', 'zones', 'territories', 'subsidiaries', 'regions'];
        foreach (Schema::getTableListing(Schema::getCurrentSchemaListing(), schemaQualified: false) as $name) {
            foreach (Schema::getForeignKeys($name) as $foreign) {
                if (in_array($foreign['foreign_table'], $retired, true)) {
                    Schema::table($name, fn (Blueprint $t) => $t->dropForeign($foreign['name'] ?? $foreign['columns']));
                }
            }
        }
        foreach (['objectifs', 'campaigns', 'campaign_equipes'] as $name) {
            if (Schema::hasColumn($name, 'team_id')) {
                foreach (Schema::getIndexes($name) as $index) {
                    if (!$index['primary'] && in_array('team_id', $index['columns'], true)) {
                        Schema::table($name, fn (Blueprint $t) => $t->dropIndex($index['name']));
                    }
                }
                Schema::table($name, fn (Blueprint $t) => $t->dropColumn('team_id'));
            }
        }
        Schema::disableForeignKeyConstraints();

        // 2. Drop legacy geography tables
        $tables = [
            'teams',
            'sectors',
            'zones',
            'territories',
            'subsidiaries',
            'regions'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
                echo "✓ Dropped table: {$table}\n";
            }
        }

        Schema::enableForeignKeyConstraints();
        echo "✓✓✓ Legacy geography tables removed successfully ✓✓✓\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This would recreate the tables, but we won't implement it
        // as this is a destructive migration intended to simplify the system
        echo "⚠ Cannot reverse: This migration removes legacy tables\n";
    }
};
