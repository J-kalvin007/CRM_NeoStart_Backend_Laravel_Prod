<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contact_types', function (Blueprint $table) {
            $table->string('statut')->default('valide')->after('is_active');
        });

        Schema::table('account_types', function (Blueprint $table) {
            $table->string('statut')->default('valide')->after('is_active');
        });

        // Mettre à jour les enregistrements existants pour s'aligner sur 'is_active'
        DB::statement("UPDATE contact_types SET statut = CASE WHEN is_active = 1 THEN 'valide' ELSE 'inactif' END");
        DB::statement("UPDATE account_types SET statut = CASE WHEN is_active = 1 THEN 'valide' ELSE 'inactif' END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_types', function (Blueprint $table) {
            $table->dropColumn('statut');
        });
        
        Schema::table('account_types', function (Blueprint $table) {
            $table->dropColumn('statut');
        });
    }
};
