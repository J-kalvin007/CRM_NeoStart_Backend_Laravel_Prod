<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cette migration vide toutes les permissions et les associations rôle-permissions
     * pour permettre une réinitialisation complète du système de permissions.
     */
    public function up(): void
    {
        // Nettoyer le cache Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // TRUNCATE refuse les tables référencées par une clé étrangère sous MySQL
        Schema::disableForeignKeyConstraints();

        // Supprimer toutes les associations rôle-permissions et utilisateur-permissions
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_permissions')->truncate();

        // Supprimer toutes les permissions
        DB::table('permissions')->truncate();

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * En cas de rollback, on ne peut pas restaurer les permissions précédentes
     * car elles ont été définitivement supprimées.
     */
    public function down(): void
    {
        // Pas de rollback possible - les permissions sont perdues
        // Il faudra relancer le seeder approprié
    }
};
