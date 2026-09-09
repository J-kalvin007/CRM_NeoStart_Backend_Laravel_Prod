<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ZoneController extends Controller
{
    #[OA\Get(
        path: '/api/v1/zones',
        summary: 'Liste des zones',
        tags: ['Geography'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Succès')
        ]
    )]
    public function index()
    {
        return response()->json(Zone::with('sectors')->get());
    }

    #[OA\Post(
        path: '/api/v1/zones',
        summary: 'Créer une zone',
        tags: ['Geography'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['territory_id', 'name', 'code'],
                properties: [
                    new OA\Property(property: 'territory_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'code', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Zone créée avec succès'),
            new OA\Response(response: 422, description: 'Données invalides')
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'territory_id' => 'required|exists:territories,id',
            'name' => 'required|string',
            'code' => 'required|string|unique:zones',
        ]);
        return response()->json(Zone::create($validated), 201);
    }

    #[OA\Get(
        path: '/api/v1/zones/{id}',
        summary: 'Afficher une zone spécifique',
        tags: ['Geography'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Succès')
        ]
    )]
    public function show(Zone $zone)
    {
        return response()->json($zone->load('sectors'));
    }

    #[OA\Put(
        path: '/api/v1/zones/{id}',
        summary: 'Mettre à jour une zone',
        tags: ['Geography'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Succès')
        ]
    )]
    public function update(Request $request, Zone $zone)
    {
        $zone->update($request->all());
        return response()->json($zone);
    }

    #[OA\Delete(
        path: '/api/v1/zones/{id}',
        summary: 'Supprimer une zone',
        tags: ['Geography'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Succès')
        ]
    )]
    public function destroy(Zone $zone)
    {
        $zone->delete();
        return response()->json(null, 204);
    }
}
