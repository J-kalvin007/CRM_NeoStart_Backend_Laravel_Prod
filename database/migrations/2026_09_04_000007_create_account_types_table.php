<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table de configuration des types de Comptes.
     * Remplace l'enum 'account_type' figé dans la table accounts.
     * L'administrateur peut ajouter/modifier les types sans toucher au code.
     *
     * Exemples secteur Pharma : Pharmacie, Hôpital, Clinique, Grossiste
     * Exemples secteur B2B    : Client Grand Compte, Revendeur, Distributeur
     * Exemples secteur Agro   : Coopérative, Silo, Négoce
     */
    public function up(): void
    {
        Schema::create('account_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique()->comment('Identifiant technique : pharmacie, hopital...');
            $table->string('libelle')->comment('Libellé affiché dans l\'interface');
            $table->text('description')->nullable();
            $table->string('couleur', 7)->nullable()->comment('Couleur UI #RRGGBB');
            $table->string('icone')->nullable()->comment('Classe icône ou nom SVG');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0)->comment('Ordre d\'affichage dans les listes');
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_types');
    }
};
