<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traçabilité complète des mouvements de stocks d'échantillons.
     * Couvre : Réception → Attribution → Distribution → Retour → Destruction.
     */
    public function up(): void
    {
        Schema::create('sample_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            // Utilisateur ayant effectué le mouvement
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();

            // Activité associée (nullable : la réception n'a pas d'activité liée)
            $table->foreignUuid('activity_id')
                ->nullable()
                ->constrained('activities')
                ->nullOnDelete();

            // Type de mouvement (discriminateur)
            $table->enum('movement_type', [
                'reception',    // Entrée en stock depuis le siège
                'attribution',  // Attribution du siège vers le délégué
                'distribution', // Remise à un contact lors d'une visite
                'retour',       // Retour au stock
                'destruction',  // Périmés / inutilisables
                'inventaire',   // Correction d'inventaire
            ]);

            // Quantité (toujours positive, le type indique le sens)
            $table->unsignedInteger('quantity');

            // Traçabilité pharmaceutique
            $table->string('lot_number')->nullable()->comment('Numéro de lot du médicament');
            $table->date('expiry_date')->nullable()->comment('Date de péremption du lot');

            // Preuve de distribution (signature ou scan QR)
            $table->string('signature_url')->nullable();
            $table->string('qr_code')->nullable();

            // Stock résultant après mouvement (snapshot pour cohérence historique)
            $table->unsignedInteger('stock_apres')->nullable();

            $table->text('remarques')->nullable();

            // Synchro mobile offline-first
            $table->uuid('local_uuid')->nullable()->unique();

            $table->timestamps();

            $table->index('movement_type');
            $table->index('lot_number');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_movements');
    }
};
