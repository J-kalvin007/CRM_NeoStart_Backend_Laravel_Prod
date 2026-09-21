<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;
use App\Models\Contact;
use App\Models\Activity;

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
        
        $kol_count = (clone $contactsQuery)->where('is_kol', true)->count();
        
        $cible_a = (clone $contactsQuery)->where('potentiel', 'fort')->count();
        $cible_b = (clone $contactsQuery)->where('potentiel', 'moyen')->count();
        // Fallback to "faible" OR null
        $cible_c = (clone $contactsQuery)->where(function($q) {
            $q->where('potentiel', 'faible')->orWhereNull('potentiel');
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
            ]
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
        // For a true implementation, we would query `activities` and `objectifs`
        // Mocked response for now, mapped to frontend expectations.
        return response()->json([
            [
                'delegate' => [
                    'id' => '00000000-0000-0000-0000-000000000001',
                    'name' => 'Alice Doe',
                ],
                'visit_count' => 120,
                'objective_rate' => 85.5,
                'revenue' => 15000.00,
                'rank' => 1,
                'period' => 'monthly'
            ]
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
        return response()->json([
            [
                'id' => '00000000-0000-0000-0000-000000000001',
                'name' => 'Alice Doe',
                'lat' => 14.6928,
                'lng' => -17.4467,
                'status' => 'En visite',
                'last_seen' => now()->toISOString()
            ]
        ]);
    }
}
