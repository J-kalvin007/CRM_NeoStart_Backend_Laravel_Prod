<?php

namespace App\Services;

use App\Models\{Activity, Objectif, User};
use App\Models\Scopes\DynamicPerimeterScope;
use Illuminate\Http\Request;

class PerformanceOverview
{
    public function forRequest(Request $request): array
    {
        $filters = $request->validate([
            'period' => 'sometimes|in:weekly,monthly,quarterly,yearly,custom',
            'start_date' => 'required_if:period,custom|required_with:end_date|nullable|date_format:Y-m-d',
            'end_date' => 'required_with:start_date|nullable|date_format:Y-m-d|after_or_equal:start_date',
            'role_id' => 'nullable|string|max:100',
            'user_id' => 'nullable|string|max:100',
        ]);
        $period = $filters['period'] ?? 'monthly';
        [$start, $end] = match ($period) {
            'weekly' => [now()->startOfWeek(), now()->endOfWeek()],
            'quarterly' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'yearly' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
        if (!empty($filters['start_date'])) {
            $start = \Illuminate\Support\Carbon::parse($filters['start_date'])->startOfDay();
            $end = \Illuminate\Support\Carbon::parse($filters['end_date'])->endOfDay();
        }
        $viewer = $request->user();
        $global = $viewer->can('scope:global') || $viewer->hasAnyRole(['superadmin', 'super-admin', 'admin']);
        // Keep selected-country isolation, replacing only the legacy perimeter with explicit ownership.
        $people = User::withoutGlobalScope(DynamicPerimeterScope::class)
            ->when(!$global, fn ($q) => $q->whereIn('id', array_merge([$viewer->id], $viewer->teamMemberIds())))
            ->with(['roles', 'country'])->orderBy('name')->get();
        $roles = $people->flatMap(fn ($person) => $person->roles)->unique('id')->sortBy('name')->values()
            ->map(fn ($role) => ['id' => $role->id, 'name' => $role->name]);
        $options = $people->map(fn ($person) => ['id' => $person->id, 'name' => $person->name,
            'role_ids' => $person->roles->pluck('id')->values()]);
        $people = $people->filter(fn ($person) =>
            (empty($filters['role_id']) || $person->roles->contains('id', $filters['role_id'])) &&
            (empty($filters['user_id']) || $person->id === $filters['user_id']));
        $ids = $people->modelKeys();
        $objectives = Objectif::withoutGlobalScope(DynamicPerimeterScope::class)
            ->whereIn('user_id', $ids)->where('statut', '!=', 'annule')
            ->whereDate('date_debut', '<=', $end)->whereDate('date_fin', '>=', $start)
            ->get(['user_id', 'valeur_cible', 'valeur_realisee'])->groupBy('user_id');
        $peopleById = $people->keyBy('id');
        $planning = Activity::withoutGlobalScope(DynamicPerimeterScope::class)->visits()
            ->whereIn('user_id', $ids)->whereBetween('date_activite', [$start->toDateString(), $end->toDateString()])
            ->with(['contact:id,prenom,nom', 'account:id,nom'])
            ->orderBy('date_activite')->orderBy('heure_debut')
            ->get(['id', 'user_id', 'contact_id', 'account_id', 'activity_type_id', 'activity_type', 'statut',
                'date_activite', 'date_fin', 'heure_debut', 'heure_fin', 'start_time', 'end_time'])
            ->map(function ($visit) use ($peopleById) {
                $person = $peopleById->get($visit->user_id);
                return array_merge(VisitExecution::describe($visit, now()), [
                    'name' => $person->name, 'country_name' => $person->country?->name,
                    'practitioner' => $visit->contact ? trim($visit->contact->prenom.' '.$visit->contact->nom) : null,
                    'facility' => $visit->account?->nom,
                ]);
            });
        $visits = $planning->groupBy('user_id');
        $data = $people->map(function ($person) use ($objectives, $visits) {
            $assigned = $objectives->get($person->id, collect());
            $measurable = $assigned->filter(fn ($o) => $o->valeur_cible > 0);
            $rates = $measurable->map(fn ($o) => $o->valeur_realisee / $o->valeur_cible * 100);
            $execution = VisitExecution::summarize($visits->get($person->id, collect()));
            return array_merge($execution, [
                'id' => $person->id, 'name' => $person->name,
                'country_id' => $person->country_id, 'country_name' => $person->country?->name ?? 'Pays non renseigné',
                'roles' => $person->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values(),
                'objective_count' => $assigned->count(), 'measured_objectives' => $rates->count(),
                'achieved_objectives' => $rates->filter(fn ($rate) => $rate >= 100)->count(),
                'attainment_sum' => round($rates->sum(), 4),
                'attainment_rate' => $rates->isEmpty() ? null : round($rates->avg(), 1),
            ]);
        })->values();
        return ['data' => $data, 'planning' => $planning->values(), 'meta' => ['period' => $period, 'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(), 'roles' => $roles, 'users' => $options->values()]];
    }
}
