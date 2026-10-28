<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Activity;
use App\Models\Objectif;
use App\Models\Order;
use App\Models\ClmPresentationAnalytic;
use App\Models\Document;
use App\Models\PharmacovigilanceReport;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;
use App\Models\Contact;

class AnalyticsController extends Controller
{
    #[OA\Get(
        path: '/api/v1/analytics/executive',
        summary: 'Tableau de bord BI exécutif',
        security: [['bearerAuth' => []]],
        tags: ['Analytics'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données consolidées',
            )
        ]
    )]
    public function executiveSummary(Request $request)
    {
        $period = $request->input('period', 'ALL');

        // Initial queries
        $salesQuery = Sale::query();
        $contactsQuery = Contact::query();
        $activitiesQuery = Activity::query();

        // Filter by period
        if ($period !== 'ALL') {
            $salesQuery->whereYear('created_at', $period);
            $contactsQuery->whereYear('created_at', $period);
            $activitiesQuery->whereYear('created_at', $period);
        }

        $total_sales = $salesQuery->sum('amount');
        $sales_count = (clone $salesQuery)->count();
        $total_contacts = $contactsQuery->count();
        $total_visits = $activitiesQuery->count();
        
        $kol_count = (clone $contactsQuery)->whereHas('kolProfile')->count();
        
        $cible_a = (clone $contactsQuery)->whereHas('potential', fn($q) => $q->where('code', 'HIGH'))->count();
        $cible_b = (clone $contactsQuery)->whereHas('potential', fn($q) => $q->where('code', 'MED'))->count();
        // Fallback to "faible" OR null
        $cible_c = (clone $contactsQuery)->where(function($q) {
            $q->whereHas('potential', fn($sq) => $sq->where('code', 'LOW'))->orWhereNull('potential_id');
        })->count();

        return response()->json([
            'total_sales' => $total_sales,
            'sales_count' => $sales_count,
            'total_contacts' => $total_contacts,
            'total_visits' => $total_visits,
            'kol_count' => $kol_count,
            'cibles' => [
                'fort' => $cible_a,
                'moyen' => $cible_b,
                'faible' => $cible_c,
            ],
        ]);
    }
    #[OA\Get(path: '/api/v1/analytics/performance', summary: 'Objectifs et performance par pays et poste',
        security: [['bearerAuth' => []]], tags: ['Manager Analytics'],
        responses: [new OA\Response(response: 200, description: 'Performance des collaborateurs autorisés')])]
    public function performanceOverview(Request $request)
    {
        return response()->json(app(\App\Services\PerformanceOverview::class)->forRequest($request));
    }

    #[OA\Get(
        path: '/api/v1/analytics/team-performance',
        summary: 'Afficher les performances de l\'équipe du manager',
        security: [['bearerAuth' => []]],
        tags: ['Manager Analytics'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Performances de l\'équipe',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'delegate', type: 'object', properties: [
                                new OA\Property(property: 'id', type: 'string'),
                                new OA\Property(property: 'name', type: 'string'),
                            ]),
                            new OA\Property(property: 'visit_count', type: 'integer'),
                            new OA\Property(property: 'no_show_count', type: 'integer'),
                            new OA\Property(property: 'completed_visits', type: 'integer'),
                            new OA\Property(property: 'in_progress_visits', type: 'integer'),
                            new OA\Property(property: 'target_visits', type: 'integer'),
                            new OA\Property(property: 'objective_rate', type: 'number', format: 'float'),
                            new OA\Property(property: 'revenue', type: 'number', format: 'float'),
                            new OA\Property(property: 'rank', type: 'integer'),
                            new OA\Property(property: 'period', type: 'string')
                        ]
                    )
                )
            )
        ]
    )]
    public function teamPerformance(Request $request)
    {
        $overview = app(\App\Services\PerformanceOverview::class)->forRequest($request);
        $period = $overview['meta']['period'];
        $start = $overview['meta']['start_date'];
        $end = $overview['meta']['end_date'];
        $data = collect($overview['data'])->map(function ($row) use ($start, $end, $period) {
            $revenue = Order::where('user_id', $row['id'])->whereIn('status', ['confirmed', 'invoiced', 'validee', 'livree'])
                ->whereBetween('created_at', [$start.' 00:00:00', $end.' 23:59:59'])->sum('total_amount');
            return array_merge($row, [
                'delegate' => ['id' => (string) $row['id'], 'name' => $row['name'], 'sector' => null],
                'visit_count' => $row['planned_visits'],
                'target_visits' => $row['planned_visits'],
                'objective_rate' => $row['attainment_rate'],
                'no_show_count' => $row['missed_visits'],
                'revenue' => (float) $revenue, 'period' => $period,
                'period_label' => $start.' — '.$end,
            ]);
        })->sortByDesc('completed_visits')->values()->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });
        return response()->json(['data' => $data, 'meta' => array_merge($overview['meta'], [
            'start' => $start, 'end' => $end, 'target_basis' => 'actual_planning',
            'target_visits' => $data->sum('planned_visits'), 'team_target' => $data->sum('planned_visits'),
            'team_completed' => $data->sum('completed_visits'), 'team_planned' => $data->sum('planned_visits'),
        ])]);
    }

    #[OA\Get(
        path: '/api/v1/users/locations',
        summary: 'Afficher la dernière position GPS des délégués',
        security: [['bearerAuth' => []]],
        tags: ['Manager Analytics'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des positions',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'lat', type: 'number', format: 'float'),
                            new OA\Property(property: 'lng', type: 'number', format: 'float'),
                            new OA\Property(property: 'status', type: 'string'),
                            new OA\Property(property: 'last_seen', type: 'string', format: 'date-time')
                        ]
                    )
                )
            )
        ]
    )]
    public function usersLocations(Request $request)
    {
        return response()->json(app(\App\Services\DelegateLocations::class)->forViewer($request->user()));
    }

    #[OA\Get(
        path: '/api/v1/analytics/edetailing-summary',
        summary: 'Résumé des analytiques de présentation CLM',
        security: [['bearerAuth' => []]],
        tags: ['Manager Analytics'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données analytiques',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'document_id', type: 'string'),
                            new OA\Property(property: 'document_name', type: 'string'),
                            new OA\Property(property: 'views_count', type: 'integer'),
                            new OA\Property(property: 'average_duration_seconds', type: 'integer'),
                        ]
                    )
                )
            )
        ]
    )]
    public function edetailingSummary(Request $request)
    {
        $dates = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d|required_with:end_date',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date|required_with:start_date',
        ]);
        $user = auth()->user();
        
        $query = ClmPresentationAnalytic::with('document');
        
        // Filtrer par équipe si l'utilisateur n'est pas un administrateur global
        if (!$user->can('scope:global') && !$user->hasAnyRole(['superadmin', 'admin'])) {
            // Ses propres présentations et celles de son équipe (hiérarchie manager_id)
            $teamUsers = array_merge([$user->id], $user->teamMemberIds());
            $query->whereIn('user_id', $teamUsers);
        }

        \App\Support\CountryFilter::apply($query);
        if (!empty($dates['start_date'])) $query->whereBetween('presented_at', [$dates['start_date'].' 00:00:00', $dates['end_date'].' 23:59:59']);
        $analyticsData = $query->get();

        $totalSessions = $analyticsData->count();
        $totalDuration = $analyticsData->sum('total_duration_seconds');
        $completedSessions = $analyticsData->where('completed', true)->count();
        $engagementRate = $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100) : 0;
        
        // Calculer les top decks
        $deckStats = [];
        $slideStats = [];
        $totalSlidesViewed = 0;

        foreach ($analyticsData as $analytic) {
            $docId = $analytic->document_id;
            if (!isset($deckStats[$docId])) {
                $deckStats[$docId] = [
                    'document_id' => $docId,
                    'document_name' => $analytic->document->titre ?? $analytic->document->title ?? 'Document inconnu',
                    'sessions' => 0,
                    'total_duration' => 0,
                ];
            }
            $deckStats[$docId]['sessions']++;
            $deckStats[$docId]['total_duration'] += $analytic->total_duration_seconds;

            if (is_array($analytic->slides_data)) {
                foreach ($analytic->slides_data as $slide) {
                    $totalSlidesViewed++;
                    $slideIndex = $slide['slide_index'] ?? 0;
                    if (!isset($slideStats[$slideIndex])) {
                        $slideStats[$slideIndex] = [
                            'name' => 'Slide ' . $slideIndex,
                            'views' => 0,
                            'total_duration' => 0,
                        ];
                    }
                    $slideStats[$slideIndex]['views']++;
                    $slideStats[$slideIndex]['total_duration'] += ($slide['duration_seconds'] ?? 0);
                }
            }
        }

        $topDecks = collect(array_values($deckStats))->map(function($d) {
            $d['average_duration_seconds'] = $d['sessions'] > 0 ? round($d['total_duration'] / $d['sessions']) : 0;
            return $d;
        })->sortByDesc('sessions')->take(5)->values();

        $topSlides = collect(array_values($slideStats))->map(function($s) {
            $s['average_duration_seconds'] = $s['views'] > 0 ? round($s['total_duration'] / $s['views']) : 0;
            return $s;
        })->sortByDesc('views')->take(5)->values();

        return response()->json([
            'total_sessions' => $totalSessions,
            'average_duration_seconds' => $totalSessions > 0 ? round($totalDuration / $totalSessions) : 0,
            'total_slides_viewed' => $totalSlidesViewed,
            'engagement_rate' => $engagementRate,
            'top_decks' => $topDecks,
            'top_slides' => $topSlides,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/supervisor/alerts',
        summary: 'Récupérer les alertes opérationnelles (Superviseur)',
        security: [['bearerAuth' => []]],
        tags: ['Manager Analytics'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des alertes',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'late_delegates', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'high_no_show_delegates', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'urgent_pharmacovigilance', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'pending_reports', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            )
        ]
    )]
    public function alerts(Request $request)
    {
        $user = auth()->user();
        $teamUserIds = $user->teamMemberIds();

        // 1. late_delegates
        // Visites planifiées dont la date + heure de début sont dans le passé
        $now = now();
        $lateActivities = Activity::with('delegue')
            ->whereIn('user_id', $teamUserIds)
            ->where('statut', 'planifiee')
            ->where(function($query) use ($now) {
                $query->whereBetween('date_activite', [$now->copy()->subDays(7)->toDateString(), $now->copy()->subDay()->toDateString()])
                      ->orWhere(function($q) use ($now) {
                          $q->whereDate('date_activite', $now->toDateString())
                            ->whereNotNull('heure_debut')
                            ->whereTime('heure_debut', '<', $now->toTimeString());
                      });
            })
            ->get();
            
        $lateDelegates = $lateActivities->map(function($a) {
            return [
                'delegate_id' => $a->user_id,
                'delegate_name' => $a->delegue->name ?? 'Inconnu',
                'activity_id' => $a->id,
                'scheduled_date' => $a->date_activite->format('Y-m-d') . ' ' . ($a->heure_debut ?? '00:00:00'),
            ];
        })->values();

        // 2. high_no_show_delegates
        $noShows = Activity::whereIn('user_id', $teamUserIds)
            ->whereIn('statut', ['manquee', 'annulee'])
            ->whereMonth('date_activite', $now->month)
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->having('count', '>=', 3)
            ->get();
            
        $highNoShowDelegates = $noShows->map(function($n) {
            $u = User::find($n->user_id);
            return [
                'delegate_id' => $n->user_id,
                'delegate_name' => $u->name ?? 'Inconnu',
                'no_show_count' => $n->count,
            ];
        })->values();

        // 3. urgent_pharmacovigilance
        $urgentPharma = PharmacovigilanceReport::with('user')
            ->whereIn('user_id', $teamUserIds)
            ->where('severity', 'grave')
            ->where('created_at', '>=', $now->copy()->subHours(48))
            ->get();
            
        $urgentPharmacovigilance = $urgentPharma->map(function($p) {
            return [
                'report_id' => $p->id,
                'delegate_id' => $p->user_id,
                'delegate_name' => $p->user->name ?? 'Inconnu',
                'description' => $p->description_effet ?? 'Non renseigné',
                'date_signalement' => $p->created_at->format('Y-m-d H:i:s'),
            ];
        })->values();

        // Kept as an empty compatibility key: submitted reports require no decision.
        $pendingReports = collect();

        return response()->json([
            'late_delegates' => $lateDelegates,
            'high_no_show_delegates' => $highNoShowDelegates,
            'urgent_pharmacovigilance' => $urgentPharmacovigilance,
            'pending_reports' => $pendingReports,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/marketing/objections-stats',
        summary: 'Statistiques de fréquence et taux de conviction des objections',
        security: [['bearerAuth' => []]],
        tags: ['Manager Analytics'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données analytiques',
            )
        ]
    )]
    public function objectionsStats(Request $request)
    {
        $stats = DB::table('activity_objections')
            ->whereIn('activity_objections.activity_id', Activity::query()->select('activities.id'))
            ->join('objections', 'activity_objections.objection_id', '=', 'objections.id')
            ->select(
                'objections.title',
                DB::raw('count(*) as total_occurrences'),
                DB::raw('sum(case when physician_reaction = "convaincu" then 1 else 0 end) as convinced_count'),
                DB::raw('sum(case when physician_reaction = "partiellement_convaincu" then 1 else 0 end) as partial_count'),
                DB::raw('sum(case when physician_reaction = "refus" then 1 else 0 end) as refused_count')
            )
            ->groupBy('objections.id', 'objections.title')
            ->get();

        return response()->json($stats);
    }

    #[OA\Get(
        path: '/api/v1/export/performance',
        summary: 'Exporter la performance de l\'équipe aux formats Excel/PDF',
        security: [['bearerAuth' => []]],
        tags: ['Manager Analytics'],
        responses: [
            new OA\Response(response: 200, description: 'Fichier Excel ou PDF généré')
        ]
    )]
    public function exportPerformance(Request $request)
    {
        $request->validate(['format' => 'sometimes|in:xlsx,pdf']);
        $data = app(\App\Services\BusinessReportExports::class)->performance($request, $request->input('format', 'xlsx'));
        return app(\App\Services\ReportDocument::class)->download($request, $data);
    }

    #[OA\Get(
        path: '/api/v1/export/expenses',
        summary: 'Exporter le bilan des dépenses aux formats Excel/PDF',
        security: [['bearerAuth' => []]],
        tags: ['Manager Analytics'],
        responses: [
            new OA\Response(response: 200, description: 'Fichier Excel ou PDF généré')
        ]
    )]
    public function exportExpenses(Request $request)
    {
        $request->validate(['format' => 'sometimes|in:xlsx,pdf']);
        $data = app(\App\Services\BusinessReportExports::class)->expenses($request, $request->input('format', 'xlsx'));
        return app(\App\Services\ReportDocument::class)->download($request, $data);
    }

    #[OA\Get(
        path: '/api/v1/export/activities',
        summary: 'Exporter l\'état des visites au format Excel',
        security: [['bearerAuth' => []]],
        tags: ['Manager Analytics'],
        responses: [
            new OA\Response(response: 200, description: 'Fichier Excel généré')
        ]
    )]
    public function exportActivities(Request $request)
    {
        $request->validate(['format' => 'sometimes|in:xlsx,pdf']);
        $data = app(\App\Services\BusinessReportExports::class)->activities($request, 'xlsx');
        return app(\App\Services\ReportDocument::class)->download($request, $data);
    }

    #[OA\Get(
        path: '/api/v1/export/activities/pdf',
        summary: 'Exporter un fichier PDF de l\'état des visites',
        security: [['bearerAuth' => []]],
        tags: ['Manager Analytics'],
        responses: [
            new OA\Response(response: 200, description: 'Fichier PDF généré')
        ]
    )]
    public function exportActivitiesPdf(Request $request)
    {
        $request->validate(['format' => 'sometimes|in:xlsx,pdf']);
        $data = app(\App\Services\BusinessReportExports::class)->activities($request, 'pdf');
        return app(\App\Services\ReportDocument::class)->download($request, $data);
    }
}
