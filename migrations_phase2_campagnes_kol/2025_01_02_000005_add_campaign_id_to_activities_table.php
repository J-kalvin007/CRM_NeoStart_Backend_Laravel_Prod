<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // Une visite peut être rattachée à une campagne. Nullable :
            // toutes les visites ne s'inscrivent pas dans une campagne.
            $table->foreignId('campaign_id')->nullable()
                ->after('tour_plan_id')
                ->constrained('campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn('campaign_id');
        });
    }
};
