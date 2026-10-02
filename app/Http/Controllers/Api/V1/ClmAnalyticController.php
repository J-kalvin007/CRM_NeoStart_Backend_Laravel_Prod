<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ClmPresentationAnalytic;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'CLM Analytics', description: 'Gestion des statistiques de présentation (e-Detailing)')]
class ClmAnalyticController extends Controller
{
    #[OA\Post(
        path: '/api/v1/activities/{activity}/clm-analytics',
        summary: 'Enregistrer les statistiques de visionnage CLM pour une activité',
        security: [['bearerAuth' => []]],
        tags: ['CLM Analytics'],
        parameters: [
            new OA\Parameter(name: 'activity', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['document_id', 'contact_id', 'total_duration_seconds', 'slides_data'],
                properties: [
                    new OA\Property(property: 'document_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'contact_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'total_duration_seconds', type: 'integer'),
                    new OA\Property(
                        property: 'slides_data',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'slide_index', type: 'integer'),
                                new OA\Property(property: 'duration_seconds', type: 'integer')
                            ]
                        )
                    ),
                    new OA\Property(property: 'completed', type: 'boolean')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Statistiques enregistrées avec succès'),
            new OA\Response(response: 404, description: 'Activité non trouvée')
        ]
    )]
    public function store(Request $request, Activity $activity)
    {
        $validated = $request->validate([
            'document_id' => 'required|string',
            'contact_id' => 'nullable|string',
            'total_duration_seconds' => 'required|integer|min:0',
            'slides_data' => 'required|array',
            'slides_data.*.slide_index' => 'required|integer|min:0',
            'slides_data.*.duration_seconds' => 'required|integer|min:0',
            'completed' => 'nullable|boolean'
        ]);

        $docId = $validated['document_id'];
        // La présentation doit être rattachée au document réellement présenté
        if (!\Illuminate\Support\Str::isUuid($docId) || !\App\Models\Document::whereKey($docId)->exists()) {
            return response()->json([
                'message' => 'Document de présentation inconnu.',
                'errors' => ['document_id' => ['Document de présentation inconnu.']],
            ], 422);
        }

        $contactId = $validated['contact_id'] ?? $activity->contact_id;
        if ($contactId && !\Illuminate\Support\Str::isUuid($contactId)) {
            $contactId = $activity->contact_id;
        }

        $analytic = ClmPresentationAnalytic::create([
            'activity_id' => $activity->id,
            'document_id' => $docId,
            'user_id' => $request->user()->id,
            'contact_id' => $contactId,
            'total_duration_seconds' => $validated['total_duration_seconds'],
            'slides_data' => $validated['slides_data'],
            'completed' => $validated['completed'] ?? false
        ]);

        return response()->json($analytic, 201);
    }
}
