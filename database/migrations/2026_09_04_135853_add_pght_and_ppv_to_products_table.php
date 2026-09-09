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
            $table->decimal('pght', 15, 2)->nullable()->after('prix_unitaire')->comment('Prix Grossiste Hors Taxes');
            $table->decimal('ppv', 15, 2)->nullable()->after('pght')->comment('Prix Public de Vente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['pght', 'ppv']);
        });
    }
};
