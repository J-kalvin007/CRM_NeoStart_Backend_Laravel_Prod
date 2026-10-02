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
            if (!Schema::hasColumn('activities', 'gps_start_lat')) {
                $table->decimal('gps_start_lat', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('activities', 'gps_start_lng')) {
                $table->decimal('gps_start_lng', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('activities', 'gps_start_accuracy')) {
                $table->decimal('gps_start_accuracy', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('activities', 'start_time')) {
                $table->timestamp('start_time')->nullable();
            }
            if (!Schema::hasColumn('activities', 'gps_end_lat')) {
                $table->decimal('gps_end_lat', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('activities', 'gps_end_lng')) {
                $table->decimal('gps_end_lng', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('activities', 'gps_end_accuracy')) {
                $table->decimal('gps_end_accuracy', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('activities', 'end_time')) {
                $table->timestamp('end_time')->nullable();
            }
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
