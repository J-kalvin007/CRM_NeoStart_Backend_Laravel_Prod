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
        Schema::table('organizational_units', function (Blueprint $table) {
            // Assurez-vous que l'index unique existe pour contourner le comportement Postgres
            $table->unique('id');
            
            // Drop constraint if it exists (pour MySQL où elle a été créée)
            // Dans Postgres, si la table existe sans la contrainte, le drop échouera, donc on l'entoure
            try {
                $table->dropForeign(['parent_id']);
            } catch (\Exception $e) {
                // Ignore si elle n'existe pas
            }

            $table->foreign('parent_id')
                  ->references('id')
                  ->on('organizational_units')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizational_units', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropUnique(['id']);
        });
    }
};
