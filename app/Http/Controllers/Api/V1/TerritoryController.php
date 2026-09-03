<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Territory;
use App\Http\Requests\StoreTerritoryRequest;
use App\Http\Requests\UpdateTerritoryRequest;
use App\Http\Resources\TerritoryResource;
use OpenApi\Attributes as OA;

class TerritoryController extends Controller
{
    #[OA\Get(
        path: '/api/v1/territories',
        summary: 'Liste des territoires',
        tags: ['Territories'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Succès')
        ]
    )]
    public function index()
    {
        return TerritoryResource::collection(Territory::all());
    }

    #[OA\Post(
        path: '/api/v1/territories',
        summary: 'Créer un nouveau territoire',
        tags: ['Territories'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['region_id', 'name'],
                properties: [
                    new OA\Property(property: 'region_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'manager_id', type: 'string', format: 'uuid', nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Territoire créé avec succès'),
            new OA\Response(response: 422, description: 'Données invalides')
        ]
    )]
    public function store(StoreTerritoryRequest $request)
    {
        $territory = Territory::create($request->validated());
        return new TerritoryResource($territory);
    }

    #[OA\Get(
        path: '/api/v1/territories/{id}',
        summary: 'Afficher un territoire spécifique',
        tags: ['Territories'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Succès'),
            new OA\Response(response: 404, description: 'Non trouvé')
        ]
    )]
    public function show(Territory $territory)
    {
        return new TerritoryResource($territory);
    }

    #[OA\Put(
        path: '/api/v1/territories/{id}',
        summary: 'Mettre à jour un territoire',
        tags: ['Territories'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'region_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'manager_id', type: 'string', format: 'uuid', nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Territoire mis à jour'),
            new OA\Response(response: 404, description: 'Non trouvé'),
            new OA\Response(response: 422, description: 'Données invalides')
        ]
    )]
    public function update(UpdateTerritoryRequest $request, Territory $territory)
    {
        $territory->update($request->validated());
        return new TerritoryResource($territory);
    }

    #[OA\Delete(
        path: '/api/v1/territories/{id}',
        summary: 'Supprimer un territoire',
        tags: ['Territories'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Territoire supprimé'),
            new OA\Response(response: 404, description: 'Non trouvé')
        ]
    )]
    public function destroy(Territory $territory)
    {
        $territory->delete();
        return response()->noContent();
    }
}
