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
            $table->uuid('duo_activity_type_id')->nullable()->after('duo_user_id'); // Type d'activité duo (coaching, formation, etc.)
            $table->string('duo_status')->nullable()->after('duo_activity_type_id'); // pending, accepted, rejected by manager
            $table->text('duo_rejection_reason')->nullable()->after('duo_status'); // Motif de rejet par le manager
            $table->string('duo_delegue_status')->nullable()->after('duo_rejection_reason'); // pending, accepted, rejected by delegue
            $table->text('duo_delegue_rejection_reason')->nullable()->after('duo_delegue_status'); // Motif de rejet par le délégué
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn([
                'is_duo',
                'duo_user_id',
                'duo_activity_type_id',
                'duo_status',
                'duo_rejection_reason',
                'duo_delegue_status',
                'duo_delegue_rejection_reason',
            ]);
        });
    }
};
