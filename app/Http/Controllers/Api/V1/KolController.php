<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Kol;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class KolController extends Controller
{
    #[OA\Get(
        path: '/api/v1/kols',
        summary: 'Lister les leaders d\'opinion (KOLs)',
        security: [['bearerAuth' => []]],
        tags: ['KOLs'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des KOLs',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'specialty', type: 'string'),
                            new OA\Property(property: 'institution', type: 'string'),
                            new OA\Property(property: 'influence_score', type: 'integer'),
                            new OA\Property(property: 'relationship_status', type: 'string')
                        ]
                    )
                )
            )
        ]
    )]
    public function index()
    {
        $kols = Kol::all();
        return response()->json($kols);
    }
}
