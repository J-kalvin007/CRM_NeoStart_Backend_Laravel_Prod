<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('region_id')->nullable()->after('subsidiary_id')->constrained('regions')->nullOnDelete();
            $table->foreignUuid('territory_id')->nullable()->after('region_id')->constrained('territories')->nullOnDelete();
            $table->foreignUuid('zone_id')->nullable()->after('territory_id')->constrained('zones')->nullOnDelete();
            $table->foreignUuid('sector_id')->nullable()->after('zone_id')->constrained('sectors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['sector_id']);
            $table->dropForeign(['zone_id']);
            $table->dropForeign(['territory_id']);
            $table->dropForeign(['region_id']);
            
            $table->dropColumn(['sector_id', 'zone_id', 'territory_id', 'region_id']);
        });
    }
};
