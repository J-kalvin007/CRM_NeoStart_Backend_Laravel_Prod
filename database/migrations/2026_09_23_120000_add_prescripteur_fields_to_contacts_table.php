<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajout des champs prescripteurs manquants.
     *
     * RÈGLE : tous les champs sont NULLABLE pour ne pas casser l'API mobile
     * ni les applications tierces qui ne les envoient pas.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {

            // ── Coordonnées supplémentaires ───────────────────────────────
            // Présent dans le fichier Excel mais absent de la DB
            if (!Schema::hasColumn('contacts', 'whatsapp')) {
                $table->string('whatsapp', 30)->nullable()->after('telephone');
            }
            if (!Schema::hasColumn('contacts', 'ville')) {
                $table->string('ville', 100)->nullable()->after('whatsapp');
            }
            if (!Schema::hasColumn('contacts', 'zone_quartier')) {
                $table->string('zone_quartier', 150)->nullable()->after('ville');
            }
            if (!Schema::hasColumn('contacts', 'adresse')) {
                $table->string('adresse', 255)->nullable()->after('zone_quartier');
            }

            // ── Informations de visite / ciblage ─────────────────────────
            if (!Schema::hasColumn('contacts', 'nb_patients_cible_mois')) {
                $table->unsignedSmallInteger('nb_patients_cible_mois')
                    ->nullable()
                    ->comment('Patients cibles estimés par mois')
                    ->after('score');
            }
            if (!Schema::hasColumn('contacts', 'nb_visites_recommandees_mois')) {
                $table->unsignedTinyInteger('nb_visites_recommandees_mois')
                    ->nullable()
                    ->comment('Visites recommandées par mois (fréquence cible)')
                    ->after('nb_patients_cible_mois');
            }

            // ── Produits d'intérêt ────────────────────────────────────────
            // Stockés en string libre pour rester compatibles sans FK
            if (!Schema::hasColumn('contacts', 'produit_prioritaire')) {
                $table->string('produit_prioritaire', 100)->nullable()->after('nb_visites_recommandees_mois');
            }
            if (!Schema::hasColumn('contacts', 'produit_secondaire')) {
                $table->string('produit_secondaire', 100)->nullable()->after('produit_prioritaire');
            }
            if (!Schema::hasColumn('contacts', 'autre_produit_interet')) {
                $table->string('autre_produit_interet', 100)->nullable()->after('produit_secondaire');
            }

            // ── Relation commerciale ──────────────────────────────────────
            if (!Schema::hasColumn('contacts', 'canal_prefere')) {
                $table->string('canal_prefere', 50)
                    ->nullable()
                    ->comment('Ex: Visite physique, Téléphone, WhatsApp, Email')
                    ->after('autre_produit_interet');
            }
            if (!Schema::hasColumn('contacts', 'statut_relation')) {
                $table->string('statut_relation', 50)
                    ->nullable()
                    ->comment('Ex: Actif, À contacter, Inactif, Perdu')
                    ->after('canal_prefere');
            }

            // ── Identifiant externe ───────────────────────────────────────
            // Code métier type DIV-P-001 fourni par le terrain ou l'import
            if (!Schema::hasColumn('contacts', 'code_externe')) {
                $table->string('code_externe', 50)
                    ->nullable()
                    ->unique()
                    ->comment('Identifiant prescripteur venant du fichier Excel (ex: DIV-P-001)')
                    ->after('local_uuid');
            }

            // ── Index utiles ──────────────────────────────────────────────
            $table->index('ville');
            $table->index('statut_relation');
            $table->index('produit_prioritaire');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['ville']);
            $table->dropIndex(['statut_relation']);
            $table->dropIndex(['produit_prioritaire']);

            $columns = [
                'whatsapp', 'ville', 'zone_quartier', 'adresse',
                'nb_patients_cible_mois', 'nb_visites_recommandees_mois',
                'produit_prioritaire', 'produit_secondaire', 'autre_produit_interet',
                'canal_prefere', 'statut_relation', 'code_externe',
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('contacts', $col)) {
                    if ($col === 'code_externe') {
                        $table->dropUnique(['code_externe']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
