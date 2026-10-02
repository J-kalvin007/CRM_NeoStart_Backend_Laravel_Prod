<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop in case of previous partial failure
        Schema::dropIfExists('objectif_types');
        
        // 1. Création de la table objectif_types
        Schema::create('objectif_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->comment('Ex: Visites, Chiffre d\'affaires, Ordonnances');
            $table->text('description')->nullable();
            $table->string('unit')->default('number')->comment('Visites, FCFA, %');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Modification de la table objectifs
        Schema::table('objectifs', function (Blueprint $table) {
            if (Schema::hasColumn('objectifs', 'type_objectif')) {
                // Rendre nullable l'ancien enum pour éviter les erreurs de drop si le SGBD ne le supporte pas bien, puis on tentera de le dropper
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
                ])->nullable()->change();
            }
            
            $table->foreignUuid('objectif_type_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('objectif_types')
                  ->nullOnDelete();

            $table->foreignUuid('organizational_unit_id')
                  ->nullable()
                  ->after('team_id')
                  ->constrained('organizational_units')
                  ->nullOnDelete()
                  ->comment('Périmètre assigné (Hiérarchie dynamique)');
                  
            $table->foreignUuid('assigned_by_user_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained('users')
                  ->nullOnDelete();
        });
        
        // Tentative de suppression propre de l'ancienne colonne enum (nécessite doctrine/dbal sur Laravel < 11 pour certains SGBD)
        if (Schema::hasColumn('objectifs', 'type_objectif')) {
            try {
                Schema::table('objectifs', function (Blueprint $table) {
                    $table->dropColumn('type_objectif');
                });
            } catch (\Exception $e) {
                // Ignore in case SQLite throws an exception if doctrine is missing.
                // The column was made nullable above so it won't hurt.
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('objectifs', function (Blueprint $table) {
            $table->dropForeign(['objectif_type_id']);
            $table->dropForeign(['organizational_unit_id']);
            $table->dropForeign(['assigned_by_user_id']);
            $table->dropColumn(['objectif_type_id', 'organizational_unit_id', 'assigned_by_user_id']);
        });

        Schema::dropIfExists('objectif_types');
    }
};
