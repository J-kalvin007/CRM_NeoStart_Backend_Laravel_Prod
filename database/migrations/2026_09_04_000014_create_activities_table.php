<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Activité terrain : Visite, Appel, Email, Réunion.
     * Remplace la table visites_medicales.
     * Le carrefour central du système opérationnel.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Rattachement au plan de tournée (optionnel : une activité peut être hors plan)
            $table->foreignUuid('tour_plan_id')
                ->nullable()
                ->constrained('tour_plans')
                ->nullOnDelete();

            // Délégué ayant réalisé l'activité
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();

            // Cibles (au moins un des deux renseigné)
            $table->foreignUuid('contact_id')
                ->nullable()
                ->constrained('contacts')
                ->restrictOnDelete();
            $table->foreignUuid('account_id')
                ->nullable()
                ->constrained('accounts')
                ->restrictOnDelete();

            // Nature de l'interaction (discriminateur universel)
            $table->enum('activity_type', [
                'visite',
                'appel',
                'email',
                'reunion',
                'conference',
            ])->default('visite');

            // Planification & Durée
            $table->date('date_activite');
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->unsignedSmallInteger('duree_minutes')->nullable()->comment('Calculé automatiquement');

            // Géolocalisation (pointage GPS début et fin de visite)
            $table->decimal('gps_debut_lat', 10, 7)->nullable();
            $table->decimal('gps_debut_lng', 10, 7)->nullable();
            $table->decimal('gps_fin_lat', 10, 7)->nullable();
            $table->decimal('gps_fin_lng', 10, 7)->nullable();

            // Compte-rendu général
            $table->text('notes')->nullable();
            $table->text('prochain_rdv')->nullable();

            // Workflow de statut
            $table->enum('statut', [
                'planifiee',
                'en_cours',
                'realisee',
                'manquee',
                'annulee',
            ])->default('planifiee');

            // Verrouillage : une visite réalisée ne peut plus être modifiée
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();

            // Synchro mobile offline-first (UUID généré par le mobile avant connexion)
            $table->uuid('local_uuid')->nullable()->unique();

            $table->timestamps();
            $table->softDeletes();

            $table->index('activity_type');
            $table->index('statut');
            $table->index('date_activite');
            $table->index(['user_id', 'date_activite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
