<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajout des colonnes manquantes pour les établissements / pharmacies.
     * TOUTES nullable — aucun impact sur l'API mobile existante.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {

            // ── Responsable ───────────────────────────────────────────────
            if (!Schema::hasColumn('accounts', 'responsable_prenom')) {
                $table->string('responsable_prenom', 100)->nullable()->after('responsable');
            }
            if (!Schema::hasColumn('accounts', 'responsable_nom')) {
                $table->string('responsable_nom', 100)->nullable()->after('responsable_prenom');
            }

            // ── Coordonnées supplémentaires ───────────────────────────────
            if (!Schema::hasColumn('accounts', 'whatsapp')) {
                $table->string('whatsapp', 30)->nullable()->after('telephone');
            }
            if (!Schema::hasColumn('accounts', 'zone_quartier')) {
                $table->string('zone_quartier', 150)->nullable()->after('ville');
            }

            // ── Informations commerciales ─────────────────────────────────
            if (!Schema::hasColumn('accounts', 'potentiel_commande_mois')) {
                $table->unsignedSmallInteger('potentiel_commande_mois')
                    ->nullable()
                    ->comment('Potentiel de commande estimé par mois (unités)')
                    ->after('score');
            }
            if (!Schema::hasColumn('accounts', 'grossiste_principal')) {
                $table->string('grossiste_principal', 100)
                    ->nullable()
                    ->comment('Ex: UBIPHARM, LABOREX, etc.')
                    ->after('potentiel_commande_mois');
            }

            // ── Produits disponibles (booléens optionnels) ────────────────
            // Correspond aux colonnes "Oui/Non" du fichier Excel pharmacies
            if (!Schema::hasColumn('accounts', 'produits_disponibles')) {
                $table->json('produits_disponibles')
                    ->nullable()
                    ->comment('JSON: {"CEDIN_125": true, "CEDIN_250": true, ...}')
                    ->after('grossiste_principal');
            }

            // ── Identifiant externe ───────────────────────────────────────
            if (!Schema::hasColumn('accounts', 'code_externe')) {
                $table->string('code_externe', 50)
                    ->nullable()
                    ->unique()
                    ->comment('Code métier externe (import Excel)')
                    ->after('local_uuid');
            }

            // ── Délégué assigné ───────────────────────────────────────────
            // user_id existe déjà mais on ajoute delegue_id explicite
            // pour distinguer le délégué affecté de l'auteur de la création
            if (!Schema::hasColumn('accounts', 'delegue_id')) {
                $table->foreignUuid('delegue_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $columns = [
                'responsable_prenom', 'responsable_nom',
                'whatsapp', 'zone_quartier',
                'potentiel_commande_mois', 'grossiste_principal',
                'produits_disponibles', 'code_externe',
            ];

            // Supprimer la FK d'abord
            if (Schema::hasColumn('accounts', 'delegue_id')) {
                $table->dropForeign(['delegue_id']);
                $table->dropColumn('delegue_id');
            }

            foreach ($columns as $col) {
                if (Schema::hasColumn('accounts', $col)) {
                    if ($col === 'code_externe') $table->dropUnique(['code_externe']);
                    $table->dropColumn($col);
                }
            }
        });
    }
};
