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
        Schema::table('activities', function (Blueprint $table) {
            $table->decimal('gps_debut_accuracy', 8, 2)->nullable()->after('gps_debut_lng');
            $table->decimal('gps_fin_accuracy', 8, 2)->nullable()->after('gps_fin_lng');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['gps_debut_accuracy', 'gps_fin_accuracy']);
        });
    }
};
