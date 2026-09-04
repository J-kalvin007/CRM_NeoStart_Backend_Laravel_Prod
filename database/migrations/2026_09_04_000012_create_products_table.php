<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogue Produits — Architecture universelle.
     * product_type_id = FK vers la table de configuration product_types.
     * meta = JSON pour attributs sectoriels (ex Pharma: molecule, dosage, amm, forme_galenique).
     * Aucun champ pharma-spécifique dans le schéma SQL.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Ancrage multi-tenant
            $table->foreignUuid('subsidiary_id')->constrained('subsidiaries')->restrictOnDelete();

            // Type configurable par l'Admin (remplace l'enum product_type figé)
            $table->foreignUuid('product_type_id')
                ->nullable()
                ->constrained('product_types')
                ->nullOnDelete();

            // Identité catalogue universelle
            $table->string('nom');
            $table->string('code')->nullable()->unique()->comment('Code SKU / Référence interne');
            $table->string('marque')->nullable();
            $table->string('gamme')->nullable()->comment('Gamme / Ligne produit');

            // Commercial
            $table->decimal('prix_unitaire', 15, 2)->nullable();
            $table->string('devise', 10)->default('XOF');

            // Gestion de stock (piloté par product_type.is_stockable)
            $table->unsignedInteger('stock_actuel')->default(0);
            $table->unsignedInteger('stock_alerte')->default(0)->comment('Seuil alerte stock bas');

            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->enum('statut', ['brouillon', 'actif', 'archive'])->default('actif');

            // Attributs sectoriels libres (sans pollution du schéma SQL)
            // Pharma ex: {"molecule": "Amoxicilline", "dosage": "500mg", "forme": "Comprimé",
            //             "numero_amm": "AMM-20241234", "date_amm": "2024-01-15",
            //             "classe_therapeutique": "Anti-infectieux"}
            // B2B ex:    {"reference_fournisseur": "REF-ABC", "poids_kg": 2.5, "dimensions": "30x20x10"}
            // Agro ex:   {"variete": "DK6222", "traitement_semences": true, "densite_semis": "70000 graines/ha"}
            $table->json('meta')->nullable()->comment('Attributs configurables par secteur d\'activité');

            $table->timestamps();
            $table->softDeletes();

            $table->index('statut');
            $table->index('marque');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
