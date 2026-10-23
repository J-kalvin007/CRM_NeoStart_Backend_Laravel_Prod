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
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // A delayed migration must also preserve newer statuses such as 'refused'.
        DB::statement("ALTER TABLE coaching_sessions MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('coaching_sessions')->where('status', 'planifie')->update(['status' => 'active']);
        DB::table('coaching_sessions')->where('status', 'cloture')->update(['status' => 'completed']);
        DB::statement("ALTER TABLE coaching_sessions MODIFY COLUMN status ENUM('draft', 'active', 'completed') NOT NULL DEFAULT 'draft'");
    }
};
