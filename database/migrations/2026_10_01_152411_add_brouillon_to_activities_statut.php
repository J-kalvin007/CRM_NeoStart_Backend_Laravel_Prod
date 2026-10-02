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
        // MySQL : conversion de l'ENUM en chaîne ; autres pilotes (SQLite des tests) : la colonne accepte déjà toute chaîne
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE activities MODIFY statut VARCHAR(50) DEFAULT 'brouillon'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE activities MODIFY statut ENUM('planifiee', 'en_cours', 'realisee', 'manquee', 'annulee') DEFAULT 'planifiee'");
        }
    }
};
