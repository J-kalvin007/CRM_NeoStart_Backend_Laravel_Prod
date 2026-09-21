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
    #[OA\Get(
        path: '/api/v1/therapeutic-classes/{id}',
        summary: 'Afficher une classe thérapeutique',
        security: [['bearerAuth' => []]],
        tags: ['Therapeutic Classes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails de la classe'),
            new OA\Response(response: 404, description: 'Classe non trouvée')
        ]
    )]
    public function show($id)
    {
        return response()->json(TherapeuticClass::findOrFail($id));
    }

    #[OA\Put(
        path: '/api/v1/therapeutic-classes/{id}',
        summary: 'Mettre à jour une classe thérapeutique',
        security: [['bearerAuth' => []]],
        tags: ['Therapeutic Classes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Classe mise à jour'),
            new OA\Response(response: 404, description: 'Classe non trouvée')
        ]
    )]
    public function update(Request $request, $id)
    {
        $class = TherapeuticClass::findOrFail($id);
        
        $validated = $request->validate([
            'nom' => 'sometimes|required|string|unique:therapeutic_classes,nom,'.$id,
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $class->update($validated);
        return response()->json($class);
    }

    #[OA\Delete(
        path: '/api/v1/therapeutic-classes/{id}',
        summary: 'Supprimer une classe thérapeutique',
        security: [['bearerAuth' => []]],
        tags: ['Therapeutic Classes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Classe supprimée'),
            new OA\Response(response: 404, description: 'Classe non trouvée')
        ]
    )]
    public function destroy($id)
    {
        TherapeuticClass::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
