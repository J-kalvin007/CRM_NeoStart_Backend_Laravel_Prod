<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Http\Requests\StoreRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Http\Resources\RegionResource;
use OpenApi\Attributes as OA;

class RegionController extends Controller
{
    #[OA\Get(
        path: '/api/v1/regions',
        summary: 'Liste des régions',
        tags: ['Regions'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Succès')
        ]
    )]
    public function index()
    {
        return RegionResource::collection(Region::all());
    }

    #[OA\Post(
        path: '/api/v1/regions',
        summary: 'Créer une nouvelle région',
        tags: ['Regions'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['subsidiary_id', 'name'],
                properties: [
                    new OA\Property(property: 'subsidiary_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'manager_id', type: 'string', format: 'uuid', nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Région créée avec succès'),
            new OA\Response(response: 422, description: 'Données invalides')
        ]
    )]
    public function store(StoreRegionRequest $request)
    {
        $region = Region::create($request->validated());
        return new RegionResource($region);
    }

    #[OA\Get(
        path: '/api/v1/regions/{id}',
        summary: 'Afficher une région spécifique',
        tags: ['Regions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Succès'),
            new OA\Response(response: 404, description: 'Non trouvé')
        ]
    )]
    public function show(Region $region)
    {
        return new RegionResource($region);
    }

    #[OA\Put(
        path: '/api/v1/regions/{id}',
        summary: 'Mettre à jour une région',
        tags: ['Regions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'subsidiary_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'manager_id', type: 'string', format: 'uuid', nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Région mise à jour'),
            new OA\Response(response: 404, description: 'Non trouvé'),
            new OA\Response(response: 422, description: 'Données invalides')
        ]
    )]
    public function update(UpdateRegionRequest $request, Region $region)
    {
        $region->update($request->validated());
        return new RegionResource($region);
    }

    #[OA\Delete(
        path: '/api/v1/regions/{id}',
        summary: 'Supprimer une région',
        tags: ['Regions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Région supprimée'),
            new OA\Response(response: 404, description: 'Non trouvé')
        ]
    )]
    public function destroy(Region $region)
    {
        $region->delete();
        return response()->noContent();
    }
}
