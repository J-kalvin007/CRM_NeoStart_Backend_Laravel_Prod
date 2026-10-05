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
            if (!Schema::hasColumn('products', 'prix_cession')) {
                $table->decimal('prix_cession', 10, 2)->nullable()->after('pght');
            }
            if (!Schema::hasColumn('products', 'devise')) {
                $table->string('devise', 10)->default('FCFA')->nullable()->after('ppv');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'prix_cession')) {
                $table->dropColumn('prix_cession');
            }
            if (Schema::hasColumn('products', 'devise')) {
                $table->dropColumn('devise');
            }
        });
    }
};
