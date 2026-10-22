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

    #[OA\Get(
        path: '/api/v1/trainings/{id}',
        summary: 'Afficher le détail d\'une formation',
        security: [['bearerAuth' => []]],
        tags: ['Trainings'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail de la formation'),
            new OA\Response(response: 404, description: 'Formation non trouvée'),
        ]
    )]
    public function show(string $id)
    {
        $training = Training::findOrFail($id);
        return response()->json($training);
    }

    #[OA\Patch(
        path: '/api/v1/trainings/{id}/progress',
        summary: 'Mettre à jour la progression d\'une formation',
        security: [['bearerAuth' => []]],
        tags: ['Trainings'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['progress'],
                properties: [
                    new OA\Property(property: 'progress', type: 'number', format: 'float'),
                    new OA\Property(property: 'status', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Progression mise à jour'),
            new OA\Response(response: 404, description: 'Formation non trouvée'),
        ]
    )]
    public function updateProgress(Request $request, string $id)
    {
        $training = Training::findOrFail($id);

        $data = $request->validate([
            'progress' => 'required|numeric|min:0|max:100',
            'status' => 'sometimes|string|max:50',
        ]);

        $training->progress = $data['progress'];
        if (array_key_exists('status', $data)) {
            $training->status = $data['status'];
        }
        $training->save();

        return response()->json($training);
    }
}
