<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table pivot : détail du compte-rendu produit par activité.
     * Trace : quel produit présenté, combien d'échantillons laissés, niveau d'intérêt.
     */
    public function up(): void
    {
        Schema::create('activity_product', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('activity_id')
                ->constrained('activities')
                ->restrictOnDelete();

            $table->foreignUuid('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            // Quantité d'échantillons / supports distribués lors de la visite
            $table->unsignedInteger('quantity')->default(0);

            // Intérêt manifesté par le contact pour ce produit
            $table->enum('customer_interest', ['faible', 'moyen', 'fort'])->nullable();

            // Commentaire spécifique au produit pendant cette activité
            $table->text('remarques')->nullable();

            $table->timestamps();

            // Un produit ne peut être tracé qu'une fois par activité
            $table->unique(['activity_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_product');
    }
};
