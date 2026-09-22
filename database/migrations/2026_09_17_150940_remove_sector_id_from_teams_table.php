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
        Schema::table('teams', function (Blueprint $table) {
            if (Schema::hasColumn('teams', 'sector_id')) {
                // Si une foreign key existe, il faut la dropper avant de drop la colonne (selon votre BDD)
                $table->dropForeign(['sector_id']); // Décommenter si constraint
                $table->dropColumn('sector_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->uuid('sector_id')->nullable();
        });
    }
};
