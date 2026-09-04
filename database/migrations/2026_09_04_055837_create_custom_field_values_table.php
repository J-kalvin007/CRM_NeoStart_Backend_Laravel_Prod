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
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Lien avec le champ personnalisé
            $table->foreignUuid('custom_field_id')->constrained()->cascadeOnDelete();
            
            // Lien avec l'entité spécifique (Polymorphique)
            $table->uuid('entity_id');
            $table->string('entity_type', 100);
            $table->index(['entity_id', 'entity_type']);
            
            // Valeur (Stockée en texte, castée selon le type du champ par le modèle)
            $table->text('value')->nullable();
            
            $table->timestamps();
            
            // Une entité ne peut avoir qu'une seule valeur pour un même champ
            $table->unique(['custom_field_id', 'entity_id', 'entity_type'], 'cf_entity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
