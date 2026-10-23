<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\TourPlan;
use Illuminate\Support\Facades\DB;

class WeeklyPlanningDecision
{
    public function apply(TourPlan $plan, bool $approved, ?string $reason = null): TourPlan
    {
        return DB::transaction(function () use ($plan, $approved, $reason) {
            $plan = TourPlan::withoutGlobalScopes()->lockForUpdate()->findOrFail($plan->id);
            abort_unless($plan->statut === 'soumis', 422, 'Ce planning n’est pas en attente de décision.');
            abort_if(!$approved && mb_strlen(trim((string) $reason)) < 2, 422, 'Le motif du refus est obligatoire.');
            $plan->update([
                'statut' => $approved ? 'valide' : 'rejete',
                'valide_par' => auth()->id(), 'valide_le' => now(),
                'commentaire_validation' => $approved ? null : $reason,
            ]);
            Activity::withoutGlobalScopes()->whereNull('deleted_at')
                ->where('tour_plan_id', $plan->id)->where('user_id', $plan->user_id)
                ->where('outside_weekly_plan', false)->where('statut', 'en_attente')
                ->update([
                    'statut' => $approved ? 'validee' : 'refusee',
                    'validated_by_id' => auth()->id(), 'validation_date' => now(),
                    'rejection_reason' => $approved ? null : $reason,
                ]);
            return $plan;
        });
    }
}
