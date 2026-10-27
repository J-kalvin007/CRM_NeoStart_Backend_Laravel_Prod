<?php

namespace App\Services;

use App\Models\{Activity, Expense, User};
use App\Models\Scopes\DynamicPerimeterScope;
use Illuminate\Http\Request;

/** Server queried exports retained for API consumers; the web also renders its exact filtered view. */
class BusinessReportExports
{
    private function userIds(Request $request): array
    {
        $viewer = $request->user();
        $global = $viewer->can('scope:global') || $viewer->hasAnyRole(['superadmin', 'super-admin', 'admin']);
        return User::withoutGlobalScope(DynamicPerimeterScope::class)
            ->when(!$global, fn ($q) => $q->whereIn('id', array_merge([$viewer->id], $viewer->teamMemberIds())))
            ->pluck('id')->all();
    }

    private function document(Request $request, string $title, array $columns, array $rows, string $format): array
    {
        return ['format' => $format, 'title' => $title,
            'filters' => collect($request->only(['period', 'user_id', 'delegate_id', 'role_id', 'statut', 'date_debut', 'date_fin', 'start_date', 'end_date', 'search']))
                ->map(fn ($value, $label) => ['label' => $label, 'value' => (string)$value])->values()->all(),
            'summary' => [['label' => 'Nombre de lignes', 'value' => (string)count($rows)]],
            'sections' => [['title' => 'Détail', 'columns' => $columns, 'rows' => $rows]]];
    }

    public function activities(Request $request, string $format): array
    {
        $query = Activity::withoutGlobalScope(DynamicPerimeterScope::class)->visits()
            ->whereIn('user_id', $this->userIds($request))
            ->with(['user.country', 'contact.account', 'account', 'products', 'sampleMovements.product'])
            ->when($request->delegate_id ?? $request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->statut, fn ($q, $status) => $q->where('statut', $status))
            ->when($request->date_debut, fn ($q, $date) => $q->whereDate('date_activite', '>=', $date))
            ->when($request->date_fin, fn ($q, $date) => $q->whereDate('date_activite', '<=', $date))
            ->when($request->boolean('reports_only'), fn ($q) => $q->publishedReports());
        $activities = $query->orderByDesc('date_activite')->get();
        if (in_array($request->sort_by, ['delegue', 'delegue_asc'])) $activities = $activities->sortBy(fn ($a) => $a->user?->name);
        if (in_array($request->sort_by, ['country', 'country_asc'])) $activities = $activities->sortBy(fn ($a) => $a->user?->country?->name);
        $rows = $activities->map(fn ($act) => [
            $act->date_activite?->format('d/m/Y'), $act->heure_debut, $act->heure_fin,
            $act->user?->name, $act->user?->country?->name,
            $act->contact ? trim($act->contact->prenom.' '.$act->contact->nom) : null,
            $act->account?->nom ?? $act->contact?->account?->nom, $act->statut,
            $act->hasPublishedReport() ? 'Soumis' : 'Non soumis',
            $act->hasPublishedReport() ? $act->report_content : null,
            $act->hasPublishedReport() ? $act->products->pluck('nom')->join('; ') : null,
            $act->hasPublishedReport() ? $act->sampleMovements->map(fn ($sm) => ($sm->product?->nom ?? '').' × '.$sm->quantity)->join('; ') : null,
        ])->values()->all();
        return $this->document($request, 'Visites et comptes rendus', ['Date', 'Début', 'Fin', 'Collaborateur', 'Pays', 'Praticien', 'Établissement', 'Statut visite', 'Compte rendu', 'Contenu complet', 'Produits', 'Échantillons'], $rows, $format);
    }

    public function expenses(Request $request, string $format): array
    {
        $rows = Expense::withoutGlobalScope(DynamicPerimeterScope::class)->whereIn('user_id', $this->userIds($request))
            ->with('user.country')->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->status ?? $request->statut, fn ($q, $status) => $q->where('status', $status))
            ->when($request->date_debut, fn ($q, $date) => $q->whereDate('expense_date', '>=', $date))
            ->when($request->date_fin, fn ($q, $date) => $q->whereDate('expense_date', '<=', $date))
            ->orderByDesc('expense_date')->get()->map(fn ($e) => [
                $e->id, $e->user?->name, $e->user?->country?->name, $e->type, (float)$e->amount,
                $e->currency, $e->status, $e->expense_date?->format('d/m/Y'), $e->title, $e->rejection_reason,
            ])->all();
        return $this->document($request, 'Dépenses', ['Référence', 'Collaborateur', 'Pays', 'Type', 'Montant', 'Devise', 'Statut', 'Date', 'Titre', 'Motif de décision'], $rows, $format);
    }

    public function performance(Request $request, string $format): array
    {
        $overview = app(PerformanceOverview::class)->forRequest($request);
        $rows = collect($overview['data'])->map(fn ($row) => [
            $row['name'], $row['country_name'], collect($row['roles'])->pluck('name')->join(', '),
            $row['objective_count'], $row['measured_objectives'], $row['achieved_objectives'], $row['attainment_rate'],
            $row['planned_visits'], $row['completed_visits'], $row['missed_visits'],
            $row['remaining_visits'], $row['execution_rate'], $row['overdue_visits'], $row['upcoming_visits'],
        ])->all();
        $doc = $this->document($request, 'Analyse et performance', ['Collaborateur', 'Pays', 'Poste', 'Objectifs attribués', 'Objectifs mesurables', 'Objectifs atteints', 'Niveau d’atteinte (%)', 'Visites prévues', 'Visites réalisées', 'Visites manquées', 'Écart au planning : non réalisées', 'Réalisation du planning (%)', 'En retard', 'À venir'], $rows, $format);
        $doc['filters'][] = ['label' => 'Du', 'value' => $overview['meta']['start_date']];
        $doc['filters'][] = ['label' => 'Au', 'value' => $overview['meta']['end_date']];
        $doc['sections'][] = ['title' => 'Planning comparé à la réalisation',
            'columns' => ['Collaborateur', 'Pays', 'Praticien', 'Établissement', 'Date prévue', 'Début prévu', 'Fin prévue', 'Début réel', 'Fin réelle', 'Écart début (minutes)', 'Réalisation', 'Inclus dans le planning'],
            'rows' => collect($overview['planning'])->map(fn ($visit) => [
                $visit['name'], $visit['country_name'], $visit['practitioner'], $visit['facility'], $visit['planned_date'],
                $visit['planned_start'], $visit['planned_end'], $visit['actual_start'], $visit['actual_end'],
                $visit['start_delay_minutes'], $visit['state_label'], $visit['counted'],
            ])->all()];

        return $doc;
    }

    public function coaching(Request $request, array $dashboard, string $kind, string $format): array
    {
        $rows = collect($dashboard['sessions'] ?? [])->map(fn ($s) => [
            $s['date_duo'] ?? null, data_get($s, 'evaluated.name') ?? data_get($s, 'delegate.name'),
            data_get($s, 'evaluated.country.name') ?? data_get($s, 'delegate.country.name'),
            data_get($s, 'evaluator.name') ?? data_get($s, 'supervisor.name'),
            $s['note_sur_20'] ?? null, $s['respect_canevas_pct'] ?? null,
            $s['points_forts'] ?? $s['strengths'] ?? null, $s['axes_amelioration'] ?? $s['weaknesses'] ?? null,
            $s['commentaires_superviseur'] ?? null, $s['plan_action_recommande'] ?? $s['action_plan'] ?? null,
        ])->all();
        return $this->document($request, 'Rapport '.strtoupper($kind), ['Date', 'Collaborateur évalué', 'Pays', 'Évaluateur', 'Note / 20', 'Respect du canevas (%)', 'Points forts', 'Axes d’amélioration', 'Commentaires complets', 'Plan d’action complet'], $rows, $format);
    }
}
