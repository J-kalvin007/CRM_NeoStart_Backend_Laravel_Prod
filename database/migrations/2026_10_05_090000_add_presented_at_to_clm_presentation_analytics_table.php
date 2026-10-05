<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date et heure réelles de la présentation (début du visionnage sur la tablette).
     */
    public function up(): void
    {
        Schema::table('clm_presentation_analytics', function (Blueprint $table) {
            $table->timestamp('presented_at')->nullable()->after('completed');
        });
    }

    public function down(): void
    {
        Schema::table('clm_presentation_analytics', function (Blueprint $table) {
            $table->dropColumn('presented_at');
        });
    }
};
