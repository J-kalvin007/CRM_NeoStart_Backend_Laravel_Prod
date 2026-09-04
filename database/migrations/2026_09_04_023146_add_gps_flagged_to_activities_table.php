<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->boolean('gps_flagged')->default(false)->after('is_locked')
                  ->comment('True si la distance GPS lors de la clôture dépassait le seuil autorisé.');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('gps_flagged');
        });
    }
};
