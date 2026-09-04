<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Objectifs de performance assignés à un utilisateur ou à une équipe.
     * Couvre : Objectifs Visites, Couverture, CA Ventes, Échantillons, Campagnes...
     */
    public function up(): void
    {
        Schema::create('objectifs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Assignation : à un utilisateur spécifique (Délégué) OU à une équipe
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignUuid('team_id')
                ->nullable()
                ->constrained('teams')
                ->restrictOnDelete();

            // Ancrage temporel
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('periode')->nullable()->comment('Ex: S42-2026, Q3-2026, Oct-2026');

            // Discriminateur : nature de l'objectif
            $table->enum('type_objectif', [
                'nombre_visites',
                'couverture_contacts',
                'ca_ventes',
                'nb_commandes',
                'nb_echantillons',
                'nb_nouveaux_contacts',
                'nb_activites_kol',
                'campagne',
                'autre',
            ])->default('nombre_visites');

            // Valeurs
            $table->decimal('valeur_cible', 15, 2);
            $table->decimal('valeur_realisee', 15, 2)->default(0);
            $table->string('unite')->nullable()->comment('visites, %, XOF, unités...');

            // Taux de réalisation calculé en % (peut être mis à jour par un Job)
            $table->decimal('taux_realisation', 5, 2)->default(0)
                ->comment('Calculé automatiquement : valeur_realisee / valeur_cible * 100');

            // Lien optionnel à un produit ou une campagne spécifique
            $table->foreignUuid('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            $table->text('description')->nullable();
            $table->enum('statut', ['actif', 'atteint', 'depasse', 'non_atteint', 'annule'])
                ->default('actif');

            $table->timestamps();
            $table->softDeletes();

            $table->index('type_objectif');
            $table->index('statut');
            $table->index(['user_id', 'date_debut', 'date_fin']);
            $table->index(['team_id', 'date_debut', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objectifs');
    }
};
