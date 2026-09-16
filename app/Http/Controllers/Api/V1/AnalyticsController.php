<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Activity;
use App\Models\Objectif;
use App\Models\Order;
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
        $teamUsers = $user->team_id 
            ? User::where('team_id', $user->team_id)->get() 
            : User::all();

        $data = $teamUsers->map(function ($delegue) use ($request) {
            $visitCount = Activity::where('user_id', $delegue->id)
                ->where('activity_type', 'visite')
                ->where('statut', 'realisee')
                ->whereMonth('date_activite', now()->month)
                ->count();

            $objectif = Objectif::where('user_id', $delegue->id)
                ->where('type_objectif', 'nombre_visites')
                ->first();

            $target = $objectif->valeur_cible ?? 1;
            $rate = min(100, round(($visitCount / max(1, $target)) * 100, 1));

            $revenue = Order::where('user_id', $delegue->id)
                ->whereIn('status', ['confirmed', 'invoiced', 'validee', 'livree'])
                ->sum('total_amount');

            return [
                'delegate' => [
                    'id'   => $delegue->id,
                    'name' => $delegue->name,
                ],
                'visit_count'    => $visitCount,
                'objective_rate' => $rate,
                'revenue'        => (float) $revenue,
                'period'         => $request->get('period', 'monthly'),
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
}
