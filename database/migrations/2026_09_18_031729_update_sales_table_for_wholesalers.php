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
        Schema::table('sales', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->change();
            $table->string('mois_concerne')->nullable()->after('sale_date');
            $table->text('commentaires')->nullable()->after('mois_concerne');
            $table->json('products_json')->nullable()->after('commentaires');
            $table->uuid('local_uuid')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['mois_concerne', 'commentaires', 'products_json', 'local_uuid']);
        });
    }
};
