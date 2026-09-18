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

class AnalyticsController extends Controller
{
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
        $teamUsers = ($user && $user->team_id) 
            ? User::where('team_id', $user->team_id)->get() 
            : User::all();

        if ($teamUsers->isEmpty()) {
            $teamUsers = User::take(10)->get();
        }

        $data = $teamUsers->map(function ($delegue) use ($request) {
            $baseActivities = Activity::where('user_id', $delegue->id)
                ->where('activity_type', 'visite');

            $completedVisits = (clone $baseActivities)->whereIn('statut', ['realisee', 'effectuee', 'validee'])->count();
            $inProgressVisits = (clone $baseActivities)->where('statut', 'en_cours')->count();
            $noShowCount = (clone $baseActivities)->whereIn('statut', ['manquee', 'annulee'])->count();
            $visitCount = $completedVisits;

            $objectifQuery = Objectif::where('user_id', $delegue->id);
            if (\Illuminate\Support\Facades\Schema::hasColumn('objectifs', 'type_objectif')) {
                $objectifQuery->where('type_objectif', 'nombre_visites');
            }
            $objectif = $objectifQuery->first();

            $target = (int) ($objectif->valeur_cible ?? 20);
            $rate = min(100, round(($visitCount / max(1, $target)) * 100, 1));

            $revenue = Order::where('user_id', $delegue->id)
                ->whereIn('status', ['confirmed', 'invoiced', 'validee', 'livree'])
                ->sum('total_amount');

            return [
                'delegate' => [
                    'id'   => (string) $delegue->id,
                    'name' => $delegue->name,
                ],
                'visit_count'        => $visitCount,
                'no_show_count'      => $noShowCount,
                'completed_visits'   => $completedVisits,
                'in_progress_visits' => $inProgressVisits,
                'target_visits'      => $target,
                'objective_rate'     => $rate,
                'revenue'            => (float) $revenue,
                'period'             => $request->get('period', 'monthly'),
            ];
        })->sortByDesc('visit_count')->values()->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });

        return response()->json($data);
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
        $user = auth()->user();
        $teamUsers = $user->team_id 
            ? User::where('team_id', $user->team_id)->get() 
            : User::all();

        $locations = $teamUsers->map(function ($u) {
            $lastActivity = Activity::where('user_id', $u->id)
                ->whereNotNull('gps_debut_lat')
                ->latest('updated_at')
                ->first();

            return [
                'id'        => $u->id,
                'name'      => $u->name,
                'lat'       => $lastActivity ? (float) $lastActivity->gps_debut_lat : 5.3450,
                'lng'       => $lastActivity ? (float) $lastActivity->gps_debut_lng : -3.9820,
                'status'    => ($lastActivity && $lastActivity->statut === 'en_cours') ? 'En visite' : 'Disponible',
                'last_seen' => $lastActivity ? $lastActivity->updated_at->toISOString() : now()->toISOString(),
            ];
        });

        return response()->json($locations);
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
        
        $query = ClmPresentationAnalytic::with('document')
            ->select('document_id', DB::raw('count(*) as views_count'), DB::raw('avg(total_duration_seconds) as average_duration'))
            ->groupBy('document_id');

        // Filtrer par équipe si le manager est connecté
        if ($user->hasRole('Manager') && $user->team_id) {
            $teamUsers = User::where('team_id', $user->team_id)->pluck('id');
            $query->whereIn('user_id', $teamUsers);
        }

        $analytics = $query->get()->map(function ($item) {
            return [
                'document_id' => $item->document_id,
                'document_name' => $item->document->title ?? 'Document inconnu',
                'views_count' => $item->views_count,
                'average_duration_seconds' => round($item->average_duration),
            ];
        });

        return response()->json($analytics);
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
        $teamUserIds = $user->team_id 
            ? User::where('team_id', $user->team_id)->pluck('id') 
            : User::pluck('id');

        // 1. late_delegates
        // Visites planifiées dont la date + heure de début sont dans le passé
        $now = now();
        $lateActivities = Activity::with('delegue')
            ->whereIn('user_id', $teamUserIds)
            ->where('statut', 'planifiee')
            ->where(function($query) use ($now) {
                $query->whereDate('date_activite', '<', $now->toDateString())
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
        // Assuming 'gravite' field exists and 'grave' is the value
        $urgentPharma = PharmacovigilanceReport::with('user')
            ->whereIn('user_id', $teamUserIds)
            ->where('gravite', 'Grave')
            ->where('created_at', '>=', $now->subHours(48))
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
            ->whereIn('statut', ['realisee', 'en_attente']) // Assuming realisée requires validation
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
}
