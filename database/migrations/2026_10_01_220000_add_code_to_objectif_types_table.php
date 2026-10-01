<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Code stable des types d'objectif (nombre_visites, ca_ventes…) utilisé par l'application mobile.
     */
    public function up(): void
    {
        Schema::table('objectif_types', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('objectif_types', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
