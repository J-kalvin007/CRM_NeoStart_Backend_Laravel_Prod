<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table de configuration des types de Contacts.
     * Remplace le champ 'specialite' figé dans la table contacts.
     * Configurable par l'administrateur par secteur d'activité.
     *
     * Exemples secteur Pharma  : Cardiologue, Pédiatre, Généraliste, Pharmacien
     * Exemples secteur B2B     : Directeur Achats, Responsable Logistique
     * Exemples secteur Agro    : Agronome, Chef d'exploitation
     */
    public function up(): void
    {
        Schema::create('contact_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique()->comment('Identifiant technique : cardiologue, acheteur...');
            $table->string('libelle')->comment('Libellé affiché dans l\'interface');
            $table->text('description')->nullable();
            $table->string('couleur', 7)->nullable();
            $table->string('icone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_types');
    }
};
