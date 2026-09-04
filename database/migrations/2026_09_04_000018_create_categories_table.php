<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catégories / Tags — Architecture universelle.
     * Le champ 'type' est une string libre (plus d'enum figé).
     * Configuré via l'Admin : 'specialite', 'segment', 'classe_produit'...
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('nom');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // String libre au lieu d'un enum figé
            // Exemples Pharma : 'specialite_medicale', 'classe_therapeutique'
            // Exemples B2B    : 'secteur_activite', 'segment_client'
            // Exemples Agro   : 'type_culture', 'region_agricole'
            $table->string('type')->default('general')->comment('Groupement libre configurable par l\'Admin');

            $table->string('couleur', 7)->nullable()->comment('Couleur UI #RRGGBB');
            $table->string('icone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
        });

        // Pivot Contact ↔ Category
        Schema::create('contact_category', function (Blueprint $table) {
            $table->foreignUuid('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->primary(['contact_id', 'category_id']);
        });

        // Pivot Account ↔ Category
        Schema::create('account_category', function (Blueprint $table) {
            $table->foreignUuid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->primary(['account_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_category');
        Schema::dropIfExists('contact_category');
        Schema::dropIfExists('categories');
    }
};
