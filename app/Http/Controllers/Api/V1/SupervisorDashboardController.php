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
        if ($subordinates->isEmpty()) {
            // Fallback for testing: if user has no subordinates, just show their own data
            $subordinateIds = [$user->id];
            $subordinates = collect([$user]);
        } else {
            $subordinateIds = $subordinates->pluck('id')->toArray();
        }
        
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
}
