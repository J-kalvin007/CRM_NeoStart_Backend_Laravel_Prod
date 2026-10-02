<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La nature d'un DUO (coaching | formation) est portée par activities.duo_activity_type_id
 * (référence activity_types), utilisée par le modèle et ActivityController. La migration
 * 2026_10_01_173531 ne créait qu'une colonne texte duo_activity_type : on ajoute la référence
 * là où elle manque (sans effet sur une base qui l'a déjà).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('activities', 'duo_activity_type_id')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->uuid('duo_activity_type_id')->nullable()->after('duo_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activities', 'duo_activity_type_id')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->dropColumn('duo_activity_type_id');
            });
        }
    }
};
