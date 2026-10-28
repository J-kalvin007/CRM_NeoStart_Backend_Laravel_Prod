<?php

namespace App\Services;

use App\Models\{Activity, Objectif, User};
use App\Models\Scopes\DynamicPerimeterScope;
use Illuminate\Http\Request;

class PerformanceOverview
{
    public function forRequest(Request $request, ?array $userIds = null): array
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
            ->when($userIds !== null, fn ($q) => $q->whereIn('id', $userIds))
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
            ->with(['contact:id,prenom,nom,account_id', 'contact.account:id,nom', 'contacts' => fn ($q) => $q->select('contacts.id', 'contacts.prenom', 'contacts.nom'), 'account:id,nom'])
            ->orderBy('date_activite')->orderBy('heure_debut')
            ->get(['id', 'user_id', 'contact_id', 'account_id', 'activity_type_id', 'activity_type', 'statut',
                'date_activite', 'date_fin', 'heure_debut', 'heure_fin', 'start_time', 'end_time', 'report_status'])
            ->map(function ($visit) use ($peopleById) {
                $person = $peopleById->get($visit->user_id);
                $execution = VisitExecution::describe($visit, now());
                $duration = $visit->start_time && $visit->end_time && $visit->end_time->gte($visit->start_time)
                    ? round($visit->start_time->diffInMinutes($visit->end_time), 1) : null;
                return array_merge($execution, [
                    'name' => $person->name, 'country_name' => $person->country?->name,
                    'practitioner' => collect([$visit->contact])->merge($visit->contacts)->filter()->map(fn ($contact) => trim($contact->prenom.' '.$contact->nom))->filter()->unique()->join(', ') ?: null,
                    'contact_ids' => $visit->contacts->pluck('id')->push($visit->contact_id)->filter()->unique()->values(),
                    'facility' => $visit->account?->nom ?? $visit->contact?->account?->nom, 'contact_id' => $visit->contact_id, 'account_id' => $visit->account_id ?? $visit->contact?->account_id,
                    'report_submitted' => $execution['state'] === 'completed' && $visit->hasPublishedReport(),
                    'actual_duration_minutes' => $execution['state'] === 'completed' ? $duration : null,
                ]);
            });
        $visits = $planning->groupBy('user_id');
        $data = $people->map(function ($person) use ($objectives, $visits) {
            $assigned = $objectives->get($person->id, collect());
            $measurable = $assigned->filter(fn ($o) => $o->valeur_cible > 0);
            $rates = $measurable->map(fn ($o) => $o->valeur_realisee / $o->valeur_cible * 100);
            $personVisits = $visits->get($person->id, collect());
            $execution = VisitExecution::summarize($personVisits);
            $completed = $personVisits->where('state', 'completed');
            $durations = $completed->pluck('actual_duration_minutes')->filter(fn ($value) => $value !== null);
            $timed = $completed->filter(fn ($visit) => $visit['start_delay_minutes'] !== null);
            $submitted = $completed->where('report_submitted', true)->count();
            return array_merge($execution, [
                'id' => $person->id, 'name' => $person->name,
                'country_id' => $person->country_id, 'country_name' => $person->country?->name ?? 'Pays non renseigné',
                'roles' => $person->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values(),
                'objective_count' => $assigned->count(), 'measured_objectives' => $rates->count(),
                'achieved_objectives' => $rates->filter(fn ($rate) => $rate >= 100)->count(),
                'attainment_sum' => round($rates->sum(), 4),
                'attainment_rate' => $rates->isEmpty() ? null : round($rates->avg(), 1),
                'submitted_reports' => $submitted, 'pending_reports' => $completed->count() - $submitted,
                'duration_sum_minutes' => $durations->sum(), 'duration_sample_count' => $durations->count(),
                'on_time_visits' => $timed->filter(fn ($visit) => $visit['started_on_time'] === true)->count(),
                'timed_visits' => $timed->count(),
            ]);
        })->values();
        $countries = $people->filter(fn ($person) => $person->country)->map(fn ($person) => ['id' => $person->country_id, 'name' => $person->country->name])->unique('id')->values();
        $selected = \App\Support\CountryFilter::resolveCountryId();
        if ($global && !$selected && in_array(\App\Support\CountryFilter::selectedValue(), [null, '', 'group', 'default'], true)) {
            $countries = \App\Models\Country::orderBy('name')->get(['id', 'name']);
        } elseif ($selected && $countries->isEmpty()) {
            $countries = \App\Models\Country::whereKey($selected)->get(['id', 'name']);
        }
        return ['data' => $data, 'planning' => $planning->values(), 'meta' => ['period' => $period, 'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(), 'country_options' => $countries, 'roles' => $roles, 'users' => $options->values()]];
    }
}
