<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExecutiveOverview
{
    public function forRequest(Request $request): array
    {
        $currentRequest = clone $request;
        $legacy = $request->input('period');
        if ($legacy === 'ALL') {
            $start = Activity::visits()->min('date_activite') ?: now()->toDateString();
            $end = max(now()->toDateString(), (string) (Activity::visits()->max('date_activite') ?: now()->toDateString()));
            $currentRequest->merge(['period' => 'custom', 'start_date' => Carbon::parse($start)->toDateString(), 'end_date' => Carbon::parse($end)->toDateString()]);
        } elseif (is_string($legacy) && preg_match('/^\d{4}$/', $legacy)) {
            $currentRequest->merge(['period' => 'custom', 'start_date' => $legacy.'-01-01', 'end_date' => $legacy.'-12-31']);
        }
        $overview = app(PerformanceOverview::class)->forRequest($currentRequest);
        $start = Carbon::parse($overview['meta']['start_date']);
        $end = Carbon::parse($overview['meta']['end_date']);
        $days = (int) $start->diffInDays($end) + 1;
        $previousRequest = clone $currentRequest;
        $previousRequest->merge(['period' => 'custom', 'start_date' => $start->copy()->subDays($days)->toDateString(), 'end_date' => $start->copy()->subDay()->toDateString()]);
        $previous = app(PerformanceOverview::class)->forRequest($previousRequest);
        $summary = $this->summarize($overview);
        $before = $this->summarize($previous);
        $deltas = [];
        foreach (['planned_visits', 'completed_visits', 'missed_visits', 'submitted_reports', 'achieved_objectives'] as $key) {
            $deltas[$key] = ['difference' => $summary[$key] - $before[$key], 'previous' => $before[$key],
                'change_percent' => $before[$key] > 0 ? round(($summary[$key] - $before[$key]) * 100 / $before[$key], 1) : null];
        }
        $planning = collect($overview['planning']);
        $countries = collect($overview['data'])->groupBy(fn ($row) => $row['country_id'] ?: 'none')->map(function ($rows) use ($planning) {
            return array_merge(['id' => $rows->first()['country_id'], 'name' => $rows->first()['country_name']],
                $this->summarize(['data' => $rows, 'planning' => $planning->whereIn('user_id', $rows->pluck('id')->all())]));
        })->values();
        $daily = $planning->where('counted', true)->groupBy('planned_date')->map(fn ($rows, $date) => [
            'date' => $date, 'planned' => $rows->count(), 'completed' => $rows->where('state', 'completed')->count(),
            'missed' => $rows->where('state', 'missed')->count(), 'in_progress' => $rows->where('state', 'in_progress')->count(),
        ])->sortBy('date')->values();
        $recommendations = [];
        foreach (['missed_visits' => 'visite(s) manquée(s) à replanifier', 'overdue_visits' => 'visite(s) en retard à examiner',
            'pending_reports' => 'compte(s) rendu(s) restant à soumettre'] as $key => $label) {
            if ($summary[$key]) {
                $recommendations[] = ['code' => $key, 'count' => $summary[$key], 'text' => $summary[$key].' '.$label.'.'];
            }
        }
        $ids = collect($overview['data'])->pluck('id')->all();
        $sales = Sale::whereIn('user_id', $ids)->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
        $contacts = Contact::whereIn('user_id', $ids)->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

        return array_merge($overview, [
            'summary' => $summary, 'countries' => $countries, 'daily' => $daily, 'recommendations' => $recommendations,
            'comparison' => ['start_date' => $previous['meta']['start_date'], 'end_date' => $previous['meta']['end_date'], 'summary' => $before, 'deltas' => $deltas],
            'total_visits' => $summary['planned_visits'], 'total_contacts' => (clone $contacts)->count(),
            'total_sales' => (float) (clone $sales)->sum('amount'), 'sales_count' => (clone $sales)->count(),
            'kol_count' => (clone $contacts)->whereHas('kolProfile')->count(),
            'cibles' => ['fort' => (clone $contacts)->whereHas('potential', fn ($q) => $q->where('code', 'HIGH'))->count(),
                'moyen' => (clone $contacts)->whereHas('potential', fn ($q) => $q->where('code', 'MED'))->count(),
                'faible' => (clone $contacts)->where(fn ($q) => $q->whereNull('potential_id')->orWhereHas('potential', fn ($p) => $p->where('code', 'LOW')))->count()],
        ]);
    }

    private function summarize(array $overview): array
    {
        $rows = collect($overview['data']);
        $summary = [];
        foreach (['planned_visits', 'completed_visits', 'in_progress_visits', 'missed_visits', 'overdue_visits', 'upcoming_visits',
            'submitted_reports', 'pending_reports', 'objective_count', 'achieved_objectives'] as $key) {
            $summary[$key] = (int) $rows->sum($key);
        }
        $summary['collaborators'] = $rows->count();
        $summary['execution_rate'] = $summary['planned_visits'] ? round($summary['completed_visits'] * 100 / $summary['planned_visits'], 1) : null;
        $summary['attainment_rate'] = $rows->sum('measured_objectives') ? round($rows->sum('attainment_sum') / $rows->sum('measured_objectives'), 1) : null;
        $completed = collect($overview['planning'])->where('state', 'completed');
        $summary['visited_practitioners'] = $completed->flatMap(fn ($row) => $row['contact_ids'] ?? [])->unique()->count();
        $summary['visited_establishments'] = $completed->pluck('account_id')->filter()->unique()->count();

        return $summary;
    }
}
