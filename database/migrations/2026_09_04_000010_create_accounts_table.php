<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comptes (Entités Morales) — Architecture universelle.
     * account_type_id = FK vers la table de configuration account_types.
     * meta = JSON pour stocker des attributs sectoriels sans toucher au schéma.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Ancrage multi-tenant
            $table->foreignUuid('subsidiary_id')->constrained('subsidiaries')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();

            // Type configurable par l'Admin (plus d'enum figé)
            $table->foreignUuid('account_type_id')
                ->nullable()
                ->constrained('account_types')
                ->nullOnDelete();

            // Identité
            $table->string('nom');

            // Coordonnées
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('quartier')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('responsable')->nullable();

            // Méta-business
            $table->enum('potentiel', ['faible', 'moyen', 'fort'])->nullable();
            $table->unsignedSmallInteger('score')->nullable()->comment('Score 0-100 calculé par le moteur IA');
            $table->enum('statut', ['actif', 'inactif', 'suspendu'])->default('actif');

            // Attributs sectoriels libres (ex Pharma: autorisation_officine, nb_lits_hopital)
            $table->json('meta')->nullable()->comment('Attributs configurables par secteur d\'activité');

            // Synchro mobile offline-first
            $table->uuid('local_uuid')->nullable()->unique();

            $table->timestamps();
            $table->softDeletes();

            $table->index('statut');
            $table->index('ville');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
