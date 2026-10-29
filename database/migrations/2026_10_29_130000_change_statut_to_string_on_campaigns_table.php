<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * statut sur la table campaigns était un ENUM ('creation','validation','deploiement','execution','suivi','analyse','cloture')
     * alors que les contrôleurs et formulaires utilisent 'brouillon', 'validation', 'active', 'terminee', 'suspendue'.
     * Ce qui provoquait l'erreur SQL: 1265 Data truncated for column 'statut' at row 1 (ou check constraint en sqlite).
     * La colonne devient VARCHAR(50) par défaut 'brouillon'.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE campaigns MODIFY statut VARCHAR(50) NOT NULL DEFAULT 'brouillon'");
        } else {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->string('statut', 50)->default('brouillon')->change();
            });
        }
    }

    public function down(): void
    {
        // The restrictive ENUM cannot represent the API statuses. Preserve existing data.
    }
};
