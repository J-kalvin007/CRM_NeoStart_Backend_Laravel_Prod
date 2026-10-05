<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Simplify hierarchy to single level (Country only)
     * Add country_id directly to teams for simpler access
     */
    public function up(): void
    {
        // Check if column already exists
        if (!Schema::hasColumn('teams', 'country_id')) {
            // 1. Add country_id to teams table
            Schema::table('teams', function (Blueprint $table) {
                $table->uuid('country_id')->nullable()->after('sector_id');
                $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
            });

            // 2. Simplifier la migration des données (teams to country)
            DB::statement("
                UPDATE teams t
                SET t.country_id = (SELECT id FROM countries LIMIT 1)
                WHERE t.country_id IS NULL
            ");

            echo "✓ Teams table updated with country_id for simplified hierarchy\n";
        } else {
            echo "✓ country_id column already exists in teams table\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('teams', 'country_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropForeign(['country_id']);
                $table->dropColumn('country_id');
            });
        }
    }
};
