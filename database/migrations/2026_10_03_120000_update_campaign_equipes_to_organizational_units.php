<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Update campaign_equipes table to use organizational_units instead of teams
     */
    public function up(): void
    {
        Schema::table('campaign_equipes', function (Blueprint $table) {
            // Rename team_id to organizational_unit_id
            if (Schema::hasColumn('campaign_equipes', 'team_id')) {
                $table->renameColumn('team_id', 'organizational_unit_id');
                
                // Add foreign key to organizational_units
                $table->foreign('organizational_unit_id')->references('id')->on('organizational_units')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_equipes', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_equipes', 'organizational_unit_id')) {
                $table->dropForeign(['organizational_unit_id']);
                $table->renameColumn('organizational_unit_id', 'team_id');
            }
        });
    }
};
