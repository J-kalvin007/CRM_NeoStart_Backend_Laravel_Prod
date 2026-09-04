<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contacts (Personnes Physiques) — Architecture universelle.
     * contact_type_id = FK vers la table de configuration contact_types.
     * meta = JSON pour attributs sectoriels (ex Pharma: rpps, is_kol, specialite_detail).
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Rattachement optionnel à un Account
            $table->foreignUuid('account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();

            // Ancrage multi-tenant
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();

            // Type configurable par l'Admin (remplace specialite string figée)
            $table->foreignUuid('contact_type_id')
                ->nullable()
                ->constrained('contact_types')
                ->nullOnDelete();

            // Identité
            $table->string('nom');
            $table->string('prenom');
            $table->string('titre')->nullable()->comment('Dr., Pr., M., Mme...');

            // Coordonnées
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();

            // Méta-business
            $table->enum('potentiel', ['faible', 'moyen', 'fort'])->nullable();
            $table->enum('influence', ['faible', 'moyen', 'fort'])->nullable();
            $table->unsignedSmallInteger('score')->nullable()->comment('Score 0-100 calculé par le moteur IA');

            $table->enum('statut', ['actif', 'inactif', 'archive'])->default('actif');

            // Attributs sectoriels libres
            // Pharma ex: {"rpps": "12345", "is_kol": true, "nb_consultations_jour": 30}
            // B2B ex:    {"linkedin": "...", "budget_annuel": "500000"}
            $table->json('meta')->nullable()->comment('Attributs configurables par secteur d\'activité');

            // Synchro mobile offline-first
            $table->uuid('local_uuid')->nullable()->unique();

            $table->timestamps();
            $table->softDeletes();

            $table->index('statut');
            $table->index('nom');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
