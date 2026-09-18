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
        Schema::table('accounts', function (Blueprint $table) {
            $table->enum('statut_validation', ['en_attente', 'valide', 'rejete'])->default('en_attente')->after('statut');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->enum('statut_validation', ['en_attente', 'valide', 'rejete'])->default('en_attente')->after('statut');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('statut_validation');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('statut_validation');
        });
    }
};
