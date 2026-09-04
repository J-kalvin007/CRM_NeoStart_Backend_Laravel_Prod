<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SectorController extends Controller
{
    #[OA\Get(
        path: '/api/v1/sectors',
        summary: 'Liste des secteurs',
        tags: ['Geography'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Succès')
        ]
    )]
    public function index()
    {
        return response()->json(Sector::with('teams')->get());
    }

    #[OA\Post(
        path: '/api/v1/sectors',
        summary: 'Créer un secteur',
        tags: ['Geography'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['zone_id', 'name', 'code'],
                properties: [
                    new OA\Property(property: 'zone_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'code', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Secteur créé avec succès'),
            new OA\Response(response: 422, description: 'Données invalides')
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'name' => 'required|string',
            'code' => 'required|string|unique:sectors',
        ]);
        return response()->json(Sector::create($validated), 201);
    }

    #[OA\Get(
        path: '/api/v1/sectors/{id}',
        summary: 'Afficher un secteur spécifique',
        tags: ['Geography'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Succès')
        ]
    )]
    public function show(Sector $sector)
    {
        return response()->json($sector->load('teams'));
    }

    #[OA\Put(
        path: '/api/v1/sectors/{id}',
        summary: 'Mettre à jour un secteur',
        tags: ['Geography'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Succès')
        ]
    )]
    public function update(Request $request, Sector $sector)
    {
        $sector->update($request->all());
        return response()->json($sector);
    }

    #[OA\Delete(
        path: '/api/v1/sectors/{id}',
        summary: 'Supprimer un secteur',
        tags: ['Geography'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Succès')
        ]
    )]
    public function destroy(Sector $sector)
    {
        $sector->delete();
        return response()->json(null, 204);
    }
}
