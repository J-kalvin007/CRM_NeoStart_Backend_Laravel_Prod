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
        Schema::table('objectifs', function (Blueprint $table) {
            $table->foreignUuid('indicator_id')->nullable()->after('id')->constrained('indicators')->restrictOnDelete();
            
            // On peut supprimer type_objectif et unite puisqu'ils sont gérés par l'indicateur
            $table->dropColumn(['type_objectif', 'unite']);
        });
    }

    public function down(): void
    {
        Schema::table('objectifs', function (Blueprint $table) {
            $table->dropForeign(['indicator_id']);
            $table->dropColumn('indicator_id');
            $table->string('type_objectif')->after('periode');
            $table->string('unite')->nullable()->after('valeur_realisee');
        });
    }
};
