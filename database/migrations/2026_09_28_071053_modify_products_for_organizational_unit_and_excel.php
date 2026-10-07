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
        Schema::table('products', function (Blueprint $table) {
            // Ajouter le prix de cession manquant
            if (!Schema::hasColumn('products', 'prix_cession')) {
                $table->decimal('prix_cession', 15, 2)->nullable()->after('pght');
            }
            
            // Remplacer subsidiary_id par organizational_unit_id
            if (!Schema::hasColumn('products', 'organizational_unit_id')) {
                $table->uuid('organizational_unit_id')->nullable()->after('id');
            }
            
            if (Schema::hasColumn('products', 'subsidiary_id')) {
                // Drop la clé étrangère si elle existe en utilisant la convention Laravel
                $table->dropForeign(['subsidiary_id']);
                $table->dropColumn('subsidiary_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'subsidiary_id')) {
                $table->foreignUuid('subsidiary_id')->nullable()->after('id')->constrained('subsidiaries')->nullOnDelete();
            }
            if (Schema::hasColumn('products', 'organizational_unit_id')) {
                $table->dropForeign(['organizational_unit_id']);
                $table->dropColumn('organizational_unit_id');
            }
            if (Schema::hasColumn('products', 'prix_cession')) {
                $table->dropColumn('prix_cession');
            }
        });
    }
};
