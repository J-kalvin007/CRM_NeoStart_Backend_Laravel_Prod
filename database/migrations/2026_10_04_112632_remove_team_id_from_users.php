<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'team_id')) {
                $table->dropColumn('team_id');
            }
            if (Schema::hasColumn('users', 'subsidiary_id')) {
                $table->dropColumn('subsidiary_id');
            }
            if (Schema::hasColumn('users', 'region_id')) {
                $table->dropColumn('region_id');
            }
            if (Schema::hasColumn('users', 'territory_id')) {
                $table->dropColumn('territory_id');
            }
            if (Schema::hasColumn('users', 'zone_id')) {
                $table->dropColumn('zone_id');
            }
            if (Schema::hasColumn('users', 'sector_id')) {
                $table->dropColumn('sector_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('team_id')->nullable()->after('manager_id');
            $table->uuid('subsidiary_id')->nullable()->after('team_id');
            $table->uuid('region_id')->nullable()->after('subsidiary_id');
            $table->uuid('territory_id')->nullable()->after('region_id');
            $table->uuid('zone_id')->nullable()->after('territory_id');
            $table->uuid('sector_id')->nullable()->after('zone_id');
        });
    }
};
