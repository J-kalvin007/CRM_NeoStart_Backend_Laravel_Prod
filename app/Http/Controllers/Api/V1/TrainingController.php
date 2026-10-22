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
        summary: 'Lister les formations e-learning avec filtrage et pagination',
        security: [['bearerAuth' => []]],
        tags: ['Trainings'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des formations')
        ]
    )]
    public function index(Request $request)
    {
        $query = Training::query();

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            if ($category !== 'Toutes' && $category !== 'Tous') {
                $query->where('category', $category);
            }
        }

        if ($status = $request->input('status')) {
            if ($status !== 'Tous') {
                $query->where('status', $status);
            }
        }

        $perPage = (int) $request->input('per_page', 20);

        if ($request->has('page') || $request->has('per_page') || $request->has('search') || $request->has('category')) {
            $paginated = $query->paginate($perPage);
            return response()->json([
                'data' => $paginated->items(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
            ]);
        }

        $trainings = $query->get();
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
