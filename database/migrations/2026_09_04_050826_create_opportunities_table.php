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
        Schema::create('opportunities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Périmètre et propriétaire
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); // Le commercial / délégué
            $table->foreignUuid('subsidiary_id')->nullable()->constrained()->nullOnDelete();
            
            // Lien avec le client (Pharmacie/Hôpital) et le Produit principal
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained()->nullOnDelete();
            
            // Détails commerciaux
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('expected_revenue', 15, 2)->default(0);
            $table->integer('probability')->default(10); // Pourcentage
            $table->enum('status', ['new', 'qualification', 'proposal', 'negotiation', 'won', 'lost'])->default('new');
            
            $table->date('expected_close_date')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
