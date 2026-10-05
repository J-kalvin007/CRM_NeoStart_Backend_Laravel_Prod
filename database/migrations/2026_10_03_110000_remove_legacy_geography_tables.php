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
        // 1. Drop columns from tables that reference legacy geography tables
        Schema::table('objectifs', function (Blueprint $table) {
            if (Schema::hasColumn('objectifs', 'team_id')) {
                $table->dropColumn('team_id');
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'team_id')) {
                $table->dropColumn('team_id');
            }
        });

        Schema::table('campaign_equipes', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_equipes', 'team_id')) {
                $table->dropColumn('team_id');
            }
        });

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
