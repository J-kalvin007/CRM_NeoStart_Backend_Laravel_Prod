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
        $subsidiary_id = $request->input('subsidiary_id', 'ALL');

        // Initial queries
        $salesQuery = Sale::query();
        $contactsQuery = Contact::query();
        $activitiesQuery = Activity::query();

        // Filter by subsidiary if applicable
        if ($subsidiary_id !== 'ALL') {
            $salesQuery->where('subsidiary_id', $subsidiary_id);
            // Contacts and Activities might not directly have subsidiary_id, they belong to perimeter or users.
            // For now we filter sales, or apply a complex query if necessary.
            // A simple implementation:
            $contactsQuery->whereHas('user', function ($q) use ($subsidiary_id) {
                $q->where('subsidiary_id', $subsidiary_id);
            });
            $activitiesQuery->whereHas('delegue', function ($q) use ($subsidiary_id) {
                $q->where('subsidiary_id', $subsidiary_id);
            });
        }

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

        // Calculate sales by subsidiary
        $salesBySubQuery = Sale::query();
        if ($period !== 'ALL') {
            $salesBySubQuery->whereYear('created_at', $period);
        }
        $salesGroups = $salesBySubQuery->select('subsidiary_id', DB::raw('sum(amount) as total'))
            ->groupBy('subsidiary_id')
            ->get();
        
        $sales_by_subsidiary = [];
        foreach ($salesGroups as $sg) {
            if ($sg->subsidiary_id) {
                $sales_by_subsidiary[$sg->subsidiary_id] = (float) $sg->total;
            }
        }

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
            'sales_by_subsidiary' => $sales_by_subsidiary
        ]);
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
        $user = auth()->user();
        $period = $request->get('period', 'monthly');

        // Objectif de visites paramétrable (settings), par défaut 12/jour sur 5 jours ouvrés
        $visitsPerDay = (int) (DB::table('settings')->where('key', 'visits_per_day')->value('value') ?: 12);
        $daysPerWeek = (int) (DB::table('settings')->where('key', 'working_days_per_week')->value('value') ?: 5);
        $weeklyTarget = $visitsPerDay * $daysPerWeek;

        // Période + objectif : semaine = 60, mois = 4 semaines (240), trimestre = 12 semaines, année = 48 semaines
        $now = now();
        [$start, $end, $target, $label] = match ($period) {
            'weekly'    => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), $weeklyTarget, 'Cette semaine'],
            'quarterly' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter(), $weeklyTarget * 12, 'Ce trimestre'],
            'yearly'    => [$now->copy()->startOfYear(), $now->copy()->endOfYear(), $weeklyTarget * 48, 'Cette année'],
            default     => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), $weeklyTarget * 4, 'Ce mois'],
        };

        // L'équipe = les collaborateurs encadrés (récursif via manager_id)
        $teamUsers = $user->teamMembers()->orderBy('name')->get();

        $completedStatuses = ['realisee', 'effectuee', 'validee'];
        $excludedStatuses = ['annulee', 'refusee', 'brouillon'];

        $data = $teamUsers->map(function ($delegue) use ($start, $end, $target, $completedStatuses, $excludedStatuses, $period, $label) {
            $base = Activity::withoutGlobalScopes()
                ->where('user_id', $delegue->id)
                ->whereNull('deleted_at')
                ->whereBetween('date_activite', [$start->toDateString(), $end->toDateString()]);

            $planned = (clone $base)->whereNotIn('statut', $excludedStatuses)->count();
            $completed = (clone $base)->whereIn('statut', $completedStatuses)->count();
            $inProgress = (clone $base)->where('statut', 'en_cours')->count();
            $noShow = (clone $base)->whereIn('statut', ['manquee', 'annulee'])->count();

            $revenue = Order::where('user_id', $delegue->id)
                ->whereIn('status', ['confirmed', 'invoiced', 'validee', 'livree'])
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_amount');

            return [
                'delegate' => [
                    'id'   => (string) $delegue->id,
                    'name' => $delegue->name,
                ],
                'visit_count'        => $planned,
                'completed_visits'   => $completed,
                'in_progress_visits' => $inProgress,
                'no_show_count'      => $noShow,
                'target_visits'      => $target,
                'objective_rate'     => (int) round($completed / max(1, $target) * 100),
                'revenue'            => (float) $revenue,
                'period'             => $period,
                'period_label'       => $label,
            ];
        })->sortByDesc('completed_visits')->values()->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'period'          => $period,
                'period_label'    => $label,
                'start'           => $start->toDateString(),
                'end'             => $end->toDateString(),
                'visits_per_day'  => $visitsPerDay,
                'target_visits'   => $target,
                'team_target'     => $target * $data->count(),
                'team_completed'  => $data->sum('completed_visits'),
                'team_planned'    => $data->sum('visit_count'),
            ],
        ]);
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
        $team = auth()->user()->teamMembers()->with('scopes')->orderBy('name')->get();
        $today = now()->toDateString();

        $result = $team->map(function ($u) use ($today) {
            // Position : suivi GPS en direct (users.last_*), à défaut le dernier pointage de visite
            $lastGps = Activity::withoutGlobalScopes()
                ->where('user_id', $u->id)
                ->whereNotNull('gps_debut_lat')
                ->where('gps_debut_lat', '!=', 0)
                ->latest('updated_at')
                ->first();

            $currentVisit = Activity::withoutGlobalScopes()
                ->with(['contact', 'account'])
                ->where('user_id', $u->id)
                ->whereDate('date_activite', $today)
                ->where('statut', 'en_cours')
                ->latest('updated_at')
                ->first();

            $nextVisit = $currentVisit ?: Activity::withoutGlobalScopes()
                ->with(['contact', 'account'])
                ->where('user_id', $u->id)
                ->whereDate('date_activite', $today)
                ->where('statut', 'planifiee')
                ->orderBy('heure_debut')
                ->first();

            $liveAt = $u->last_location_at;
            $checkInAt = $lastGps?->updated_at;
            $useLive = $u->last_lat !== null && (!$checkInAt || ($liveAt && $liveAt->gte($checkInAt)));
            $lat = $useLive ? $u->last_lat : ($lastGps ? (float) $lastGps->gps_debut_lat : null);
            $lng = $useLive ? $u->last_lng : ($lastGps ? (float) $lastGps->gps_debut_lng : null);
            $positionAt = $useLive ? $liveAt : $checkInAt;

            // Position reçue il y a moins de 15 min : le délégué est suivi en temps réel
            $isLive = $liveAt && $liveAt->gt(now()->subMinutes(15));
            $status = $currentVisit ? 'en_visite' : ($isLive ? 'disponible' : 'hors_ligne');
            $statusLabel = ['en_visite' => 'En visite', 'disponible' => 'En ligne', 'hors_ligne' => 'Hors ligne'][$status];

            $contact = $nextVisit?->contact;
            $parts = array_values(array_filter(explode(' ', trim(preg_replace('/\(.*?\)/', '', $u->name)))));

            return [
                'id' => (string) $u->id,
                'name' => $u->name,
                'initials' => strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2)))) ?: 'DM',
                'photo_url' => null,
                'status' => $status,
                'status_label' => $statusLabel,
                'has_position' => $lat !== null,
                'is_live' => (bool) $isLive,
                'position_source' => $lat === null ? null : ($useLive ? 'gps_live' : 'check_in'),
                'accuracy' => $useLive ? $u->last_accuracy : null,
                'lat' => $lat,
                'lng' => $lng,
                'location_name' => $nextVisit?->account?->nom ?? '',
                'hcp_name' => $contact ? trim(($contact->titre ?: 'Dr.') . ' ' . $contact->prenom . ' ' . $contact->nom) : '',
                'hcp_spec' => '',
                'sector' => (string) ($u->scopes->first()?->name ?? ''),
                'phone' => $u->phone ?? '',
                'last_seen' => ($positionAt ?? $u->last_login_at)?->toISOString(),
                'last_check_in' => $positionAt?->format('H:i'),
                'time_left' => null,
            ];
        })->values();

        return response()->json($result);
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
        $user = auth()->user();
        
        $query = ClmPresentationAnalytic::with('document');
        
        // Filtrer par équipe si l'utilisateur n'est pas un administrateur global
        if (!$user->can('scope:global') && !$user->hasAnyRole(['superadmin', 'admin'])) {
            // Ses propres présentations et celles de son équipe (hiérarchie manager_id)
            $teamUsers = array_merge([$user->id], $user->teamMemberIds());
            $query->whereIn('user_id', $teamUsers);
        }

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

        // 4. pending_reports
        // Visites en attente de validation depuis plus de 24h
        // Assuming status = 'terminee' or 'en_attente_validation' but not 'validee'
        $pendingReportsActivities = Activity::with('delegue')
            ->whereIn('user_id', $teamUserIds)
            ->where('report_status', 'submitted')
            ->where('updated_at', '<=', now()->subHours(24))
            ->get();
            
        $pendingReports = $pendingReportsActivities->map(function($a) {
            return [
                'delegate_id' => $a->user_id,
                'delegate_name' => $a->delegue->name ?? 'Inconnu',
                'activity_id' => $a->id,
                'waiting_since' => $a->updated_at->format('Y-m-d H:i:s'),
            ];
        })->values();

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
        summary: 'Exporter la performance de l\'équipe au format CSV',
        security: [['bearerAuth' => []]],
        tags: ['Manager Analytics'],
        responses: [
            new OA\Response(response: 200, description: 'Fichier CSV généré')
        ]
    )]
    public function exportPerformance(Request $request)
    {
        $perfResponse = $this->teamPerformance($request);
        $data = json_decode($perfResponse->getContent(), true);

        $csv = "Rang,Délégué,Visites Réalisées,Non Présent,Objectif Cible,Taux (%),Chiffre d'Affaires\n";
        foreach ($data as $row) {
            $csv .= sprintf(
                "%d,\"%s\",%d,%d,%d,%.1f,%.2f\n",
                $row['rank'] ?? 0,
                str_replace('"', '""', $row['delegate']['name'] ?? 'Inconnu'),
                $row['completed_visits'] ?? 0,
                $row['no_show_count'] ?? 0,
                $row['target_visits'] ?? 0,
                $row['objective_rate'] ?? 0,
                $row['revenue'] ?? 0
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="team_performance_' . date('Y-m-d') . '.csv"',
        ]);
    }

    #[OA\Get(
        path: '/api/v1/export/expenses',
        summary: 'Exporter le bilan des dépenses au format CSV',
        security: [['bearerAuth' => []]],
        tags: ['Manager Analytics'],
        responses: [
            new OA\Response(response: 200, description: 'Fichier CSV généré')
        ]
    )]
    public function exportExpenses(Request $request)
    {
        $expenses = \App\Models\Expense::with('user')->get();

        $csv = "ID,Délégué,Catégorie,Montant,Devise,Statut,Date,Description\n";
        foreach ($expenses as $e) {
            $csv .= sprintf(
                "%d,\"%s\",\"%s\",%.2f,\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $e->id,
                str_replace('"', '""', $e->user->name ?? 'Inconnu'),
                str_replace('"', '""', $e->category ?? $e->type ?? 'Général'),
                $e->amount ?? 0,
                $e->currency ?? 'EUR',
                $e->status ?? 'soumis',
                $e->expense_date ? $e->expense_date->format('Y-m-d') : $e->created_at->format('Y-m-d'),
                str_replace('"', '""', $e->description ?? '')
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="expenses_' . date('Y-m-d') . '.csv"',
        ]);
    }
}
