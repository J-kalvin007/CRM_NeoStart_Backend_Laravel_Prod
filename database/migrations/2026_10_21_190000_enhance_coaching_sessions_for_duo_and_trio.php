<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('coaching_sessions', 'session_type')) {
                $table->string('session_type', 20)->default('duo')->after('activity_id');
            }
            if (!Schema::hasColumn('coaching_sessions', 'evaluator_id')) {
                $table->foreignUuid('evaluator_id')->nullable()->after('session_type')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('coaching_sessions', 'evaluated_id')) {
                $table->foreignUuid('evaluated_id')->nullable()->after('evaluator_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('coaching_sessions', 'note_globale')) {
                $table->decimal('note_globale', 5, 2)->nullable()->after('taux_atteinte_pct');
            }
            if (!Schema::hasColumn('coaching_sessions', 'note_sur_20')) {
                $table->decimal('note_sur_20', 4, 2)->nullable()->after('note_globale');
            }
            if (!Schema::hasColumn('coaching_sessions', 'respect_canevas_pct')) {
                $table->decimal('respect_canevas_pct', 5, 2)->nullable()->after('note_sur_20');
            }
            if (!Schema::hasColumn('coaching_sessions', 'canevas_evaluation_json')) {
                $table->json('canevas_evaluation_json')->nullable()->after('respect_canevas_pct');
            }
            if (!Schema::hasColumn('coaching_sessions', 'points_forts')) {
                $table->text('points_forts')->nullable()->after('canevas_evaluation_json');
            }
            if (!Schema::hasColumn('coaching_sessions', 'axes_amelioration')) {
                $table->text('axes_amelioration')->nullable()->after('points_forts');
            }
        });

        // Initialiser evaluator_id et evaluated_id depuis supervisor_id et delegate_id existants
        \Illuminate\Support\Facades\DB::table('coaching_sessions')
            ->whereNull('evaluator_id')
            ->update([
                'evaluator_id' => \Illuminate\Support\Facades\DB::raw('supervisor_id'),
                'evaluated_id' => \Illuminate\Support\Facades\DB::raw('delegate_id'),
                'session_type' => 'duo',
            ]);
    }

    public function down(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            $cols = [
                'session_type', 'note_globale', 'note_sur_20', 
                'respect_canevas_pct', 'canevas_evaluation_json', 
                'points_forts', 'axes_amelioration'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('coaching_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('coaching_sessions', 'evaluator_id')) {
                $table->dropForeign(['evaluator_id']);
                $table->dropColumn('evaluator_id');
            }
            if (Schema::hasColumn('coaching_sessions', 'evaluated_id')) {
                $table->dropForeign(['evaluated_id']);
                $table->dropColumn('evaluated_id');
            }
        });
    }
};
