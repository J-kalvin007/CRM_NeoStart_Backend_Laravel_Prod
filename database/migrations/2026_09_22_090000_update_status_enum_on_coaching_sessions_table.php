<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CoachingController::storeSession()/completeSession() écrivent les statuts
     * 'planifie' et 'cloture' pour le workflow de visite en duo, mais la colonne
     * ne connaissait que ['draft', 'active', 'completed'] (workflow d'évaluation).
     * En mode strict (config/database.php), l'insertion échouait donc
     * systématiquement pour toute session de duo.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE coaching_sessions MODIFY COLUMN status ENUM('draft', 'active', 'completed', 'planifie', 'cloture') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE coaching_sessions MODIFY COLUMN status ENUM('draft', 'active', 'completed') NOT NULL DEFAULT 'draft'");
    }
};
