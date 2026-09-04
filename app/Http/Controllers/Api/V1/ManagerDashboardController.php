<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagerDashboardController extends Controller
{
    public function performance(Request $request)
    {
        // Dans une vraie application, cela dépendrait de l'équipe gérée par le manager
        // Pour la démonstration, on agrège des données
        return response()->json([
            'manager' => [
                'name' => 'Secteur Ouest • Dr. Jean Dupont',
                'period' => 'Mai 2024'
            ],
            'charts' => [
                'donut' => [
                    'percentage' => 0.78,
                    'text' => '78%',
                    'sub_text' => '134/170 visites'
                ],
                'bar' => [
                    ['label' => 'S1', 'height1' => 60, 'height2' => 40],
                    ['label' => 'S2', 'height1' => 80, 'height2' => 50],
                    ['label' => 'S3', 'height1' => 100, 'height2' => 70],
                    ['label' => 'S4', 'height1' => 40, 'height2' => 20],
                ]
            ],
            'kpis' => [
                ['title' => 'Taux Couverture', 'value' => '82%', 'target' => 'Cible: 85%', 'evolution' => '+4% vs M-1', 'isPositive' => true],
                ['title' => 'Visites / Jour', 'value' => '6.4', 'target' => 'Cible: 6.0', 'evolution' => '+0.8 vs M-1', 'isPositive' => true],
                ['title' => 'Nouveaux HCPs', 'value' => '12', 'target' => 'Cible: 10', 'evolution' => '+2 vs M-1', 'isPositive' => true],
                ['title' => 'E-Detailing', 'value' => '45%', 'target' => 'Cible: 50%', 'evolution' => '-5% vs M-1', 'isPositive' => false],
            ],
            'priorities' => [
                ['name' => 'Pr. Honoré Kouassi', 'specialty' => 'Cardiologue • CHU Tokoin', 'lastVisit' => 'Il y a 45j', 'actionText' => 'Relancer', 'isUrgent' => true],
                ['name' => 'Dr. Aminata Kaboré', 'specialty' => 'Généraliste • Clinique Biasa', 'lastVisit' => 'Il y a 32j', 'actionText' => 'Planifier', 'isUrgent' => false],
            ]
        ]);
    }

    public function teamPerformance(Request $request)
    {
        $manager = $request->user();
        $period  = $request->get('period', 'monthly');

        $teamIds = DB::table('users')
            ->where('manager_id', $manager->id)
            ->pluck('id')
            ->toArray();

        if (empty($teamIds)) {
            return response()->json(['data' => [], 'period' => $period]);
        }

        $dateFrom = match($period) {
            'quarterly' => now()->startOfQuarter(),
            'yearly'    => now()->startOfYear(),
            default     => now()->startOfMonth(),
        };

        $rankings = DB::table('users')
            ->whereIn('users.id', $teamIds)
            ->leftJoin('activities', function($join) use ($dateFrom) {
                $join->on('activities.user_id', '=', 'users.id')
                     ->where('activities.date_activite', '>=', $dateFrom);
            })
            ->select(
                'users.id',
                DB::raw("CONCAT(users.prenom, ' ', users.nom) as name"),
                DB::raw('COUNT(activities.id) as visit_count'),
                DB::raw('COUNT(CASE WHEN activities.statut = \'realisee\' THEN 1 END) as completed_count')
            )
            ->groupBy('users.id', 'users.prenom', 'users.nom')
            ->orderByDesc('visit_count')
            ->get()
            ->map(function($row, $index) {
                $rate = $row->visit_count > 0
                    ? round(($row->completed_count / $row->visit_count) * 100)
                    : 0;
                return [
                    'rank'            => $index + 1,
                    'id'              => $row->id,
                    'name'            => $row->name,
                    'visit_count'     => $row->visit_count,
                    'completed_count' => $row->completed_count,
                    'objective_rate'  => $rate,
                ];
            });

        return response()->json(['data' => $rankings, 'period' => $period]);
    }
}
