<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * activity_type était un ENUM (visite, appel, email, reunion, conference) alors que l'application
     * enregistre aussi congés, absences, indisponibilités, one_to_one, rp… → insertions refusées sous MySQL.
     * Le référentiel des types est la table activity_types ; la colonne devient une chaîne libre.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE activities MODIFY activity_type VARCHAR(50) NOT NULL DEFAULT 'visite'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE activities SET activity_type = 'visite' WHERE activity_type NOT IN ('visite','appel','email','reunion','conference')");
            DB::statement("ALTER TABLE activities MODIFY activity_type ENUM('visite','appel','email','reunion','conference') NOT NULL DEFAULT 'visite'");
        }
    }
};
