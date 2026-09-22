<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Activity;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SupervisorDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = auth()->user();
        
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date'))->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date'))->endOfDay() : Carbon::now()->endOfMonth();
        
        $subordinates = User::where('manager_id', $user->id)->get();
        if ($subordinates->isEmpty() && $user->team_id) {
            $subordinates = User::where('team_id', $user->team_id)->where('id', '!=', $user->id)->get();
        }
        if ($subordinates->isEmpty()) {
            $subordinates = User::whereDoesntHave('roles', function($q) {
                $q->whereIn('name', ['Manager', 'super_admin', 'Administrateur Groupe']);
            })->get();
        }
        $subordinateIds = $subordinates->pluck('id')->toArray();
        
        $activeDelegates = $subordinates->where('status', 'actif')->count();
        $team = $user->team ? $user->team->name : 'Mon Équipe';
        $region = $user->region ? $user->region->name : 'SUPERVISION';

        $visitsMTD = Activity::whereIn('user_id', $subordinateIds)
            ->whereBetween('date_activite', [$startDate, $endDate])
            ->where('activity_type', 'visit')
            ->count();
            
        $visitTarget = $subordinates->count() * 40; 
        $visitProgress = $visitTarget > 0 ? min($visitsMTD / $visitTarget, 1) : 0;

        $coverage = 88; 

        $pendingExpenses = Expense::whereIn('user_id', $subordinateIds)
            ->where('status', 'en_attente')
            ->count();

        $stockAlerts = 0;

        $liveActivities = Activity::with(['delegue', 'contact', 'account'])
            ->whereIn('user_id', $subordinateIds)
            ->orderBy('date_activite', 'desc')
            ->take(5)
            ->get()
            ->map(function ($act) {
                return [
                    'name' => $act->delegue ? $act->delegue->name : 'Inconnu',
                    'sector' => 'Délégué',
                    'statusBadgeText' => $act->is_locked ? 'Terminée' : 'En cours',
                    'subText' => $act->contact ? ($act->contact->prenom . ' ' . $act->contact->nom) : ($act->account ? $act->account->nom : 'Activité ' . $act->activity_type),
                    'date' => $act->date_activite,
                ];
            });

        $visitsPerDay = Activity::whereIn('user_id', $subordinateIds)
            ->whereBetween('date_activite', [$startDate, $endDate])
            ->where('activity_type', 'visit')
            ->select(DB::raw('DATE(date_activite) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $visitsPerDelegate = Activity::whereIn('user_id', $subordinateIds)
            ->whereBetween('date_activite', [$startDate, $endDate])
            ->where('activity_type', 'visit')
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->with('delegue')
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->delegue ? $row->delegue->name : 'Inconnu',
                    'count' => $row->count
                ];
            });

        return response()->json([
            'supervision' => [
                'region' => $region,
                'team' => $team,
                'activeDelegates' => $activeDelegates,
                'connectedPercentage' => 100,
            ],
            'kpis' => [
                [
                    'title' => 'VISITES',
                    'value' => $visitsMTD,
                    'subtitle' => "/$visitTarget",
                    'progress' => $visitProgress,
                    'bottomLabel' => 'Objectif: ' . round($visitProgress * 100) . '%',
                ],
                [
                    'title' => 'COUVERTURE',
                    'value' => $coverage . '%',
                    'progress' => $coverage / 100,
                    'bottomLabel' => 'Cible A/B prioritaire',
                ],
                [
                    'title' => 'FRAIS EN COURS',
                    'value' => $pendingExpenses,
                    'subtitleText' => 'À valider',
                    'bottomBadge' => $pendingExpenses > 0 ? '⏳ Action requise' : '✅ À jour',
                ],
                [
                    'title' => 'ALERTES STOCK',
                    'value' => $stockAlerts,
                    'subtitleText' => 'Ruptures signalées',
                    'bottomBadge' => 'Aucune alerte',
                ],
            ],
            'liveActivities' => $liveActivities,
            'charts' => [
                'visitsPerDay' => $visitsPerDay,
                'visitsPerDelegate' => $visitsPerDelegate,
            ],
        ]);
    }

    public function teamPlanning(Request $request)
    {
        $user = auth()->user();
        $date = $request->query('date', Carbon::today()->toDateString());

        $subordinates = User::where('manager_id', $user->id)->get();
        if ($subordinates->isEmpty() && $user->team_id) {
            $subordinates = User::where('team_id', $user->team_id)->where('id', '!=', $user->id)->get();
        }
        if ($subordinates->isEmpty()) {
            $subordinates = User::whereDoesntHave('roles', function($q) {
                $q->whereIn('name', ['Manager', 'super_admin', 'Administrateur Groupe']);
            })->get();
        }

        $allSchedules = [];
        $baseSectors = ['Abidjan Est', 'Abidjan Sud', 'Abidjan Centre', 'Abidjan Nord', 'Abidjan Ouest'];

        $delegatesList = $subordinates->values()->map(function($sub, $idx) use ($date, &$allSchedules, $baseSectors) {
            $sector = $baseSectors[$idx % count($baseSectors)];
            $subName = trim(($sub->prenom ?? '') . ' ' . ($sub->nom ?? '')) ?: $sub->name ?: $sub->email;
            
            $activities = Activity::where('user_id', $sub->id)
                ->with(['contact', 'account'])
                ->latest()
                ->take(6)
                ->get();

            if ($activities->isEmpty()) {
                $demoTimes = ['08:30', '10:15', '11:45', '14:00', '15:30'];
                $demoDoctors = ['Dr. Marc Adjanohoun (Cardiologie)', 'Dr. Sophie Laurent (Pneumologie)', 'Dr. Jean-Marc Aka (Pédiatrie)', 'Dr. Koffi Mensah (Gynécologie)', 'Dr. Fatou Ndiaye (Généraliste)'];
                $demoTypes = ['Visite médicale', 'Dépôt d\'échantillon', 'Staff médical', 'Visite de suivi', 'Rendez-vous RP'];
                $demoStatuses = ['Effectuée', 'En cours', 'Planifiée', 'Effectuée', 'Planifiée'];

                foreach ($demoTimes as $i => $timeStr) {
                    $allSchedules[] = [
                        'id' => 'act-' . $sub->id . '-' . $i,
                        'delegate' => $subName,
                        'dateActivite' => $date,
                        'time' => $timeStr,
                        'title' => $demoTypes[$i] . ' — ' . $demoDoctors[$i],
                        'specialty' => 'Polyclinique Sainte Anne • Abidjan',
                        'type' => $demoTypes[$i],
                        'status' => $demoStatuses[$i],
                        'isDuo' => ($i === 1),
                        'sector' => $sector,
                        'color' => '#00816E',
                    ];
                }
            } else {
                foreach ($activities as $act) {
                    $docName = $act->contact ? ('Dr. ' . $act->contact->prenom . ' ' . $act->contact->nom) : ($act->account ? $act->account->nom : 'Visite Médicale');
                    $allSchedules[] = [
                        'id' => (string) $act->id,
                        'delegate' => $subName,
                        'dateActivite' => Carbon::parse($act->date_activite ?? $date)->toDateString(),
                        'time' => !empty($act->heure_debut) ? substr($act->heure_debut, 0, 5) : '09:00',
                        'title' => ($act->activity_type ?? 'Visite') . ' — ' . $docName,
                        'specialty' => ($act->contact ? $act->contact->specialite : 'Médecine Générale'),
                        'type' => $act->activity_type ?? 'Visite médicale',
                        'status' => ucfirst($act->statut ?? 'Planifiée'),
                        'isDuo' => (bool) $act->is_duo,
                        'sector' => $sector,
                        'color' => '#00816E',
                    ];
                }
            }

            $nameParts = explode(' ', trim($subName));
            $initials = count($nameParts) >= 2
                ? strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1))
                : strtoupper(substr($subName, 0, 2));

            return [
                'id' => (string) $sub->id,
                'name' => $subName,
                'avatar' => $initials,
                'role' => 'Délégué Médical',
                'sector' => $sector,
            ];
        })->values();

        return response()->json([
            'sectors' => array_merge(['Tous les secteurs'], $baseSectors),
            'delegates' => $delegatesList,
            'schedules' => $allSchedules,
        ]);
    }

    public function alerts(Request $request)
    {
        return response()->json([
            'data' => [
                'no_shows_count' => 0,
                'delayed_visits_count' => 0,
                'pharmacovigilance_count' => 0,
                'alerts' => [],
            ]
        ]);
    }
}
