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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Périmètre
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subsidiary_id')->nullable()->constrained()->nullOnDelete();
            
            // Relations
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('opportunity_id')->nullable()->constrained()->nullOnDelete();
            
            // Détails Commande
            $table->string('order_number')->unique();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->enum('status', ['draft', 'pending', 'confirmed', 'cancelled', 'invoiced'])->default('draft');
            $table->date('order_date');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
