<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TherapeuticClass;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TherapeuticClassController extends Controller
{
    #[OA\Get(
        path: '/api/v1/therapeutic-classes',
        summary: 'Lister les classes thérapeutiques',
        security: [['bearerAuth' => []]],
        tags: ['Therapeutic Classes'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des classes thérapeutiques')
        ]
    )]
    public function index()
    {
        return response()->json(TherapeuticClass::all());
    }

    #[OA\Post(
        path: '/api/v1/therapeutic-classes',
        summary: 'Créer une classe thérapeutique',
        security: [['bearerAuth' => []]],
        tags: ['Therapeutic Classes'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Antibiotique'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean', default: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Classe créée')
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|unique:therapeutic_classes',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        return response()->json(TherapeuticClass::create($validated), 201);
    }
}
