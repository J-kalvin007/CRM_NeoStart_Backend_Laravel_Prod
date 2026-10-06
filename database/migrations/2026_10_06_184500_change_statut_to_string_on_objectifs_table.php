<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * statut sur la table objectifs était un ENUM ('actif', 'atteint', 'depasse', 'non_atteint', 'annule')
     * alors que la validation d'attribution / approbation peut tenter d'enregistrer 'valide', 'validee', etc.
     * Ce qui provoquait l'erreur SQL: 1265 Data truncated for column 'statut' at row 1.
     * La colonne devient VARCHAR(50) par défaut 'actif'.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE objectifs MODIFY statut VARCHAR(50) NOT NULL DEFAULT 'actif'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE objectifs MODIFY statut ENUM('actif','atteint','depasse','non_atteint','annule') NOT NULL DEFAULT 'actif'");
        }
    }
};
