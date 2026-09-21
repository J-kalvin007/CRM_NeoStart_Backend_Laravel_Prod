<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Laboratory;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class LaboratoryController extends Controller
{
    #[OA\Get(
        path: '/api/v1/laboratories',
        summary: 'Lister les laboratoires',
        security: [['bearerAuth' => []]],
        tags: ['Laboratories'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des laboratoires')
        ]
    )]
    public function index()
    {
        return response()->json(Laboratory::all());
    }

    #[OA\Post(
        path: '/api/v1/laboratories',
        summary: 'Créer un laboratoire',
        security: [['bearerAuth' => []]],
        tags: ['Laboratories'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Pfizer'),
                    new OA\Property(property: 'contact_email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean', default: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Laboratoire créé')
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|unique:laboratories',
            'contact_email' => 'nullable|email',
            'is_active' => 'boolean'
        ]);

        return response()->json(Laboratory::create($validated), 201);
    }
    #[OA\Get(
        path: '/api/v1/laboratories/{id}',
        summary: 'Afficher un laboratoire',
        security: [['bearerAuth' => []]],
        tags: ['Laboratories'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails du laboratoire'),
            new OA\Response(response: 404, description: 'Laboratoire non trouvé')
        ]
    )]
    public function show($id)
    {
        return response()->json(Laboratory::findOrFail($id));
    }

    #[OA\Put(
        path: '/api/v1/laboratories/{id}',
        summary: 'Mettre à jour un laboratoire',
        security: [['bearerAuth' => []]],
        tags: ['Laboratories'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'contact_email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Laboratoire mis à jour'),
            new OA\Response(response: 404, description: 'Laboratoire non trouvé')
        ]
    )]
    public function update(Request $request, $id)
    {
        $lab = Laboratory::findOrFail($id);
        
        $validated = $request->validate([
            'nom' => 'sometimes|required|string|unique:laboratories,nom,'.$id,
            'contact_email' => 'nullable|email',
            'is_active' => 'boolean'
        ]);

        $lab->update($validated);
        return response()->json($lab);
    }

    #[OA\Delete(
        path: '/api/v1/laboratories/{id}',
        summary: 'Supprimer un laboratoire',
        security: [['bearerAuth' => []]],
        tags: ['Laboratories'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Laboratoire supprimé'),
            new OA\Response(response: 404, description: 'Laboratoire non trouvé')
        ]
    )]
    public function destroy($id)
    {
        Laboratory::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
