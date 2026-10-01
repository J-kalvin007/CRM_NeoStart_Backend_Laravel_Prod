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
            // Pour le tracking GPS
            $table->decimal('gps_start_lat', 10, 7)->nullable()->after('duo_delegue_rejection_reason');
            $table->decimal('gps_start_lng', 10, 7)->nullable()->after('gps_start_lat');
            $table->decimal('gps_start_accuracy', 10, 2)->nullable()->after('gps_start_lng');
            $table->timestamp('start_time')->nullable()->after('gps_start_accuracy');
            $table->decimal('gps_end_lat', 10, 7)->nullable()->after('start_time');
            $table->decimal('gps_end_lng', 10, 7)->nullable()->after('gps_end_lat');
            $table->decimal('gps_end_accuracy', 10, 2)->nullable()->after('gps_end_lng');
            $table->timestamp('end_time')->nullable()->after('gps_end_accuracy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn([
                'gps_start_lat',
                'gps_start_lng',
                'gps_start_accuracy',
                'start_time',
                'gps_end_lat',
                'gps_end_lng',
                'gps_end_accuracy',
                'end_time',
            ]);
        });
    }
};
