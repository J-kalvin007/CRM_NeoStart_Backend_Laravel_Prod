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
            $table->dropForeign(['country_id']);
            $table->dropForeign(['subsidiary_id']);
            $table->dropForeign(['region_id']);
            $table->dropForeign(['territory_id']);
            $table->dropForeign(['zone_id']);
            $table->dropForeign(['sector_id']);
            $table->dropForeign(['team_id']);
            
            $table->dropColumn([
                'country_id',
                'subsidiary_id',
                'region_id',
                'territory_id',
                'zone_id',
                'sector_id',
                'team_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignUuid('subsidiary_id')->nullable()->constrained('subsidiaries')->nullOnDelete();
            $table->foreignUuid('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignUuid('territory_id')->nullable()->constrained('territories')->nullOnDelete();
            $table->foreignUuid('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignUuid('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->nullOnDelete();
        });
    }
};
