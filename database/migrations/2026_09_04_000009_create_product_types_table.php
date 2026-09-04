<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table de configuration des types de Produits.
     * Remplace l'enum 'product_type' figé dans la table products.
     *
     * Exemples secteur Pharma  : Article Vente (Rx), Échantillon Gratuit, Brochure
     * Exemples secteur B2B     : Produit fini, Matière première, Emballage
     * Exemples secteur Agro    : Semence, Engrais, Produit phytosanitaire
     */
    public function up(): void
    {
        Schema::create('product_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique()->comment('Identifiant technique : article_vente, echantillon...');
            $table->string('libelle')->comment('Libellé affiché dans l\'interface');
            $table->text('description')->nullable();
            $table->boolean('is_stockable')->default(true)->comment('Ce type de produit est-il géré en stock ?');
            $table->boolean('is_distributable')->default(false)->comment('Ce type peut-il être distribué lors d\'une visite ?');
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
        Schema::dropIfExists('product_types');
    }
};
