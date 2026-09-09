<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Training;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TrainingController extends Controller
{
    #[OA\Get(
        path: '/api/v1/trainings',
        summary: 'Lister les formations e-learning',
        security: [['bearerAuth' => []]],
        tags: ['Trainings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des formations',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string'),
                            new OA\Property(property: 'title', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'duration_minutes', type: 'integer'),
                            new OA\Property(property: 'type', type: 'string'),
                            new OA\Property(property: 'progress', type: 'number'),
                            new OA\Property(property: 'status', type: 'string')
                        ]
                    )
                )
            )
        ]
    )]
    public function index()
    {
        $trainings = Training::all();
        return response()->json($trainings);
    }
}
