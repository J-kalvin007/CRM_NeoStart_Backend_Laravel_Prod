<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Objectif;
use App\Models\Activity;
use App\Models\Contact;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    #[OA\Get(
        path: '/api/v1/analytics/dashboard',
        summary: 'Obtenir les données pour le tableau de bord (Indicateurs et Objectifs)',
        security: [['bearerAuth' => []]],
        tags: ['Analytics'],
        responses: [
            new OA\Response(response: 200, description: 'Statistiques du tableau de bord')
        ]
    )]
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        // 1. Les Objectifs de l'utilisateur avec l'indicateur associé
        $objectifs = Objectif::with('indicator')
            ->where('user_id', $user->id)
            ->where('statut', 'actif')
            ->get();

        $objectivesData = $objectifs->map(function ($obj) {
            return [
                'id' => $obj->id,
                'name' => $obj->indicator ? $obj->indicator->name : 'Objectif ' . $obj->id,
                'target' => $obj->valeur_cible,
                'achieved' => $obj->valeur_realisee,
                'progress_percentage' => $obj->taux_realisation,
                'unit' => $obj->indicator ? $obj->indicator->unit : '',
            ];
        });

        // 2. Activités récentes (Aperçu)
        $recentActivities = Activity::with('contact', 'account')
            ->where('user_id', $user->id)
            ->latest('date_activite')
            ->take(5)
            ->get();

        // 3. KPIs
        $visitesCount = Activity::where('user_id', $user->id)
            ->where('activity_type', 'Visite')
            ->whereBetween('date_activite', [$startDate, $endDate])
            ->count();

        $medecinsVusCount = Activity::where('user_id', $user->id)
            ->where('activity_type', 'Visite')
            ->whereBetween('date_activite', [$startDate, $endDate])
            ->distinct('contact_id')
            ->count('contact_id');

        $totalActivitiesCount = Activity::where('user_id', $user->id)
            ->whereBetween('date_activite', [$startDate, $endDate])
            ->count();

        $eventsCount = Activity::where('user_id', $user->id)
            ->where('activity_type', 'Reunion') // Assumons que Réunion = Evénement dans ce contexte
            ->whereBetween('date_activite', [$startDate, $endDate])
            ->count();

        // 4. Activity Breakdown
        $activitiesBreakdown = Activity::where('user_id', $user->id)
            ->whereBetween('date_activite', [$startDate, $endDate])
            ->select('activity_type', DB::raw('count(*) as count'))
            ->groupBy('activity_type')
            ->get()
            ->map(function ($item) {
                return [
                    'label' => $item->activity_type,
                    'count' => $item->count
                ];
            });

        // 5. Top Doctors
        $topDoctors = Activity::with('contact')
            ->where('user_id', $user->id)
            ->whereNotNull('contact_id')
            ->select('contact_id', DB::raw('count(*) as visit_count'))
            ->groupBy('contact_id')
            ->orderByDesc('visit_count')
            ->take(4)
            ->get()
            ->map(function ($item) {
                $contact = $item->contact;
                return [
                    'id' => $contact->id ?? $item->contact_id,
                    'name' => $contact ? $contact->prenom . ' ' . $contact->nom : 'Contact ' . $item->contact_id,
                    'specialty' => $contact->specialite ?? 'Généraliste',
                    'hospital' => 'Hôpital / Clinique',
                    'visitCount' => $item->visit_count,
                    'avatarUrl' => 'https://i.pravatar.cc/150?u=' . $item->contact_id,
                ];
            });

        return response()->json([
            'objectives' => $objectivesData,
            'recent_activities' => $recentActivities,
            'kpis' => [
                'visites' => $visitesCount,
                'medecins' => $medecinsVusCount,
                'activites' => $totalActivitiesCount,
                'evenements' => $eventsCount,
            ],
            'activities_breakdown' => $activitiesBreakdown,
            'top_doctors' => $topDoctors,
        ]);
    }
}
