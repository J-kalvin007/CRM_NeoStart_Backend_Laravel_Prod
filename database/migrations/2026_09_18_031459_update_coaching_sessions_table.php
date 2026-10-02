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
        Schema::table('coaching_sessions', function (Blueprint $table) {
            $table->date('date_duo')->nullable()->after('delegate_id');
            $table->string('zone')->nullable()->after('date_duo');
            $table->string('motif')->nullable()->after('zone');
            $table->json('objectifs_prevus_json')->nullable()->after('motif');
            $table->json('resultats_constates_json')->nullable()->after('objectifs_prevus_json');
            $table->decimal('taux_atteinte_pct', 5, 2)->default(0)->after('resultats_constates_json');
            $table->text('commentaires_superviseur')->nullable()->after('taux_atteinte_pct');
            $table->text('plan_action_recommande')->nullable()->after('commentaires_superviseur');
            $table->uuid('local_uuid')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'date_duo', 'zone', 'motif', 'objectifs_prevus_json', 
                'resultats_constates_json', 'taux_atteinte_pct', 
                'commentaires_superviseur', 'plan_action_recommande', 'local_uuid'
            ]);
        });
    }
};
