<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Création de la table pivot avec les informations spécifiques au pays
        Schema::create('country_product', function (Blueprint $table) {
            $table->uuid('country_id');
            $table->uuid('product_id');
            
            $table->decimal('pght', 10, 2)->nullable();
            $table->decimal('prix_cession', 10, 2)->nullable();
            $table->decimal('ppv', 10, 2)->nullable();
            $table->string('devise', 10)->nullable();
            
            $table->integer('stock_actuel')->default(0);
            $table->integer('stock_alerte')->default(0);

            $table->primary(['country_id', 'product_id']);

            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->timestamps();
        });

        // 2. Migration des données existantes
        $products = DB::table('products')->whereNotNull('country_id')->get();
        foreach ($products as $product) {
            DB::table('country_product')->insert([
                'country_id' => $product->country_id,
                'product_id' => $product->id,
                'pght' => $product->pght,
                'prix_cession' => $product->prix_cession,
                'ppv' => $product->ppv,
                'devise' => $product->devise,
                'stock_actuel' => $product->stock_actuel,
                'stock_alerte' => $product->stock_alerte,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Suppression des colonnes devenues obsolètes dans 'products'
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn([
                'country_id', 
                'pght', 
                'prix_cession', 
                'ppv', 
                'devise', 
                'stock_actuel', 
                'stock_alerte'
            ]);
        });
    }

    public function down(): void
    {
        // Restauration des colonnes dans 'products'
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('country_id')->nullable();
            $table->decimal('pght', 10, 2)->nullable();
            $table->decimal('prix_cession', 10, 2)->nullable();
            $table->decimal('ppv', 10, 2)->nullable();
            $table->string('devise', 10)->nullable();
            $table->integer('stock_actuel')->default(0);
            $table->integer('stock_alerte')->default(0);

            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
        });

        // Migration inverse (restauration partielle, prend le premier pays trouvé)
        $pivots = DB::table('country_product')->get();
        foreach ($pivots as $pivot) {
            DB::table('products')->where('id', $pivot->product_id)->update([
                'country_id' => $pivot->country_id,
                'pght' => $pivot->pght,
                'prix_cession' => $pivot->prix_cession,
                'ppv' => $pivot->ppv,
                'devise' => $pivot->devise,
                'stock_actuel' => $pivot->stock_actuel,
                'stock_alerte' => $pivot->stock_alerte,
            ]);
        }

        Schema::dropIfExists('country_product');
    }
};
