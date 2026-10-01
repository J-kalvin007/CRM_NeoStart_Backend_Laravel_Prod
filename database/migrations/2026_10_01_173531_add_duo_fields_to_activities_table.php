<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // Pour les visites duo
            $table->boolean('is_duo')->default(false)->after('statut');
            $table->uuid('duo_user_id')->nullable()->after('is_duo'); // Superviseur ou manager participant
            $table->string('duo_activity_type')->nullable()->after('duo_user_id'); // Type d'activité duo (coaching, formation)
            $table->string('duo_status')->nullable()->after('duo_activity_type'); // pending, accepted, rejected by manager
            $table->text('duo_rejection_reason')->nullable()->after('duo_status'); // Motif de rejet par le manager
            $table->string('duo_delegue_status')->nullable()->after('duo_rejection_reason'); // pending, accepted, rejected by delegue
            $table->text('duo_delegue_rejection_reason')->nullable()->after('duo_delegue_status'); // Motif de rejet par le délégué

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

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn([
                'is_duo',
                'duo_user_id',
                'duo_activity_type',
                'duo_status',
                'duo_rejection_reason',
                'duo_delegue_status',
                'duo_delegue_rejection_reason',
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
