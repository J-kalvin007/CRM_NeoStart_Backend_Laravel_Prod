<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Objectif;
use App\Models\Activity;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

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

        // 1. Les Objectifs de l'utilisateur avec l'indicateur associé
        $objectifs = Objectif::with('indicator')
            ->where('user_id', $user->id)
            ->where('statut', 'actif')
            ->get();

        // Transformation pour ApexCharts / Nuxt
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

        return response()->json([
            'objectives' => $objectivesData,
            'recent_activities' => $recentActivities,
        ]);
    }
}
