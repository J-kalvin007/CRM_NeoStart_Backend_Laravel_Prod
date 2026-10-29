<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Objectif;
use App\Models\Scopes\DynamicPerimeterScope;
use App\Services\DelegateLocations;
use App\Services\ExecutiveOverview;
use Illuminate\Http\Request;

class DecisionReportController extends Controller
{
    public function __invoke(Request $request, ExecutiveOverview $executive)
    {
        $request->validate(['kind' => 'sometimes|in:executive,performance,visits,reports,planning,objectives,positions']);
        $kind = $request->input('kind', 'executive');
        $body = $executive->forRequest($request);
        $ids = collect($body['data'])->pluck('id')->all();
        $sections = [];
        $module = 'performance';
        $rows = $body['data'];
        $title = 'Synthèse décisionnelle';
        if (in_array($kind, ['visits', 'reports'], true)) {
            $module = $kind === 'reports' ? 'rapports' : 'visites';
            $title = $kind === 'reports' ? 'Comptes rendus soumis' : 'Rapport des visites';
            $rows = Activity::withoutGlobalScope(DynamicPerimeterScope::class)->visits()->whereIn('user_id', $ids)
                ->whereDate('date_activite', '>=', $body['meta']['start_date'])->whereDate('date_activite', '<=', $body['meta']['end_date'])
                ->when($request->filled('target_type'), fn ($q) => $q->whereIn('id', collect($body['planning'])->pluck('id')->all()))
                ->whereNotIn('statut', ['brouillon', 'draft'])->when($kind === 'reports', fn ($q) => $q->publishedReports())
                ->with(['delegue.country', 'contact.account', 'contacts', 'account'])->orderBy('date_activite')->get();
            $rows->filter(fn ($visit) => $visit->hasPublishedReport())->load(['products', 'sampleMovements.product']);
            $rows->each(fn ($visit) => $visit->setAttribute('doctorName', collect([$visit->contact])->merge($visit->contacts)->filter()->map(fn ($contact) => trim($contact->prenom.' '.$contact->nom))->filter()->unique()->join(', ') ?: $visit->account?->nom));
        } elseif ($kind === 'planning') {
            $module = 'planningExecution';
            $rows = $body['planning'];
            $title = 'Planning comparé à la réalisation';
        } elseif ($kind === 'objectives') {
            $module = 'objectifs';
            $title = 'Objectifs et niveaux d’atteinte';
            $rows = Objectif::withoutGlobalScope(DynamicPerimeterScope::class)->whereIn('user_id', $ids)
                ->whereDate('date_debut', '<=', $body['meta']['end_date'])->whereDate('date_fin', '>=', $body['meta']['start_date'])
                ->with(['user.country', 'user.roles', 'type', 'product', 'indicator'])->get();
        } elseif ($kind === 'positions') {
            $module = 'positions';
            $title = 'Dernières positions connues';
            $rows = app(DelegateLocations::class)->forViewer($request->user())->whereIn('id', $ids)->values();
        }
        if (in_array($kind, ['executive', 'performance'], true)) {
            $sections[] = ['title' => 'Performance par pays', 'columns' => ['Pays', 'Collaborateurs', 'Prévues', 'Réalisées', 'Réalisation (%)', 'Objectifs atteints'],
                'rows' => $body['countries']->map(fn ($row) => [$row['name'], $row['collaborators'], $row['planned_visits'], $row['completed_visits'], $row['execution_rate'], $row['achieved_objectives']])->all()];
            $sections[] = ['title' => 'Évolution par date prévue', 'columns' => ['Date', 'Prévues', 'Réalisées', 'Manquées', 'En cours'],
                'rows' => $body['daily']->map(fn ($row) => [$row['date'], $row['planned'], $row['completed'], $row['missed'], $row['in_progress']])->all()];
            $sections[] = ['title' => 'Points à examiner', 'columns' => ['Indicateur', 'Nombre', 'Action'],
                'rows' => collect($body['recommendations'])->map(fn ($row) => [$row['code'], $row['count'], $row['text']])->all()];
        }

        return response()->json(['module' => $module, 'title' => $title, 'rows' => $rows, 'sections' => $sections,
            'summary' => $body['summary'], 'meta' => $body['meta'],
            'note' => $kind === 'positions' ? 'Instantané des dernières positions connues ; la période ne filtre pas cet instantané.' : null]);
    }
}
