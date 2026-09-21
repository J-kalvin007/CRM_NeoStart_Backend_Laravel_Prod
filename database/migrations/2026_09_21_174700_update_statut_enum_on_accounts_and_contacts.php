<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE accounts MODIFY COLUMN statut ENUM('en_attente', 'valide', 'actif', 'inactif', 'suspendu', 'rejete') NOT NULL DEFAULT 'en_attente'");
        DB::statement("ALTER TABLE contacts MODIFY COLUMN statut ENUM('en_attente', 'valide', 'actif', 'inactif', 'archive', 'rejete') NOT NULL DEFAULT 'en_attente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
