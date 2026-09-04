<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plan de tournée : enveloppe planificatrice regroupant les visites
     * d'un commercial sur une période définie (ex: Semaine 42 - Secteur Nord).
     */
    public function up(): void
    {
        Schema::create('tour_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Propriétaire du plan (Délégué médical / Commercial)
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();

            $table->string('nom');
            $table->text('description')->nullable();

            $table->date('date_debut');
            $table->date('date_fin');

            $table->enum('statut', [
                'brouillon',
                'soumis',
                'valide',
                'en_cours',
                'termine',
                'rejete',
            ])->default('brouillon');

            // Validation manageur
            $table->foreignUuid('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->text('commentaire_validation')->nullable();

            // Statistiques agrégées (calculées / cached)
            $table->unsignedInteger('nb_activites_planifiees')->default(0);
            $table->unsignedInteger('nb_activites_realisees')->default(0);

            // Synchro mobile offline-first
            $table->uuid('local_uuid')->nullable()->unique();

            $table->timestamps();
            $table->softDeletes();

            $table->index('statut');
            $table->index(['user_id', 'date_debut', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_plans');
    }
};
