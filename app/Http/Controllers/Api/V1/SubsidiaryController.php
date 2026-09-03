<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subsidiary;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use App\Http\Requests\StoreSubsidiaryRequest;
use App\Http\Requests\UpdateSubsidiaryRequest;
use App\Http\Resources\SubsidiaryResource;

class SubsidiaryController extends Controller
{
    #[OA\Get(
        path: '/api/v1/subsidiaries',
        summary: 'Lister toutes les filiales',
        security: [['bearerAuth' => []]],
        tags: ['Subsidiaries'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des filiales récupérée avec succès'
            )
        ]
    )]
    public function index()
    {
        return SubsidiaryResource::collection(Subsidiary::with(['country', 'manager'])->get());
    }

    #[OA\Post(
        path: '/api/v1/subsidiaries',
        summary: 'Créer une nouvelle filiale',
        security: [['bearerAuth' => []]],
        tags: ['Subsidiaries'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['country_id', 'name', 'code'],
                properties: [
                    new OA\Property(property: 'country_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'code', type: 'string'),
                    new OA\Property(property: 'address', type: 'string', nullable: true),
                    new OA\Property(property: 'manager_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'status', type: 'boolean', default: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Filiale créée avec succès'
            ),
            new OA\Response(response: 422, description: 'Données invalides')
        ]
    )]
    public function store(StoreSubsidiaryRequest $request)
    {
        $subsidiary = Subsidiary::create($request->validated());

        return new SubsidiaryResource($subsidiary);
    }

    #[OA\Get(
        path: '/api/v1/subsidiaries/{id}',
        summary: 'Afficher les détails d\'une filiale',
        security: [['bearerAuth' => []]],
        tags: ['Subsidiaries'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détails de la filiale'
            ),
            new OA\Response(response: 404, description: 'Filiale non trouvée')
        ]
    )]
    public function show(string $id)
    {
        $subsidiary = Subsidiary::with(['country', 'manager'])->findOrFail($id);
        return new SubsidiaryResource($subsidiary);
    }

    #[OA\Put(
        path: '/api/v1/subsidiaries/{id}',
        summary: 'Mettre à jour une filiale',
        security: [['bearerAuth' => []]],
        tags: ['Subsidiaries'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'code', type: 'string'),
                    new OA\Property(property: 'address', type: 'string', nullable: true),
                    new OA\Property(property: 'manager_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'status', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Filiale mise à jour avec succès'
            ),
            new OA\Response(response: 404, description: 'Filiale non trouvée')
        ]
    )]
    public function update(UpdateSubsidiaryRequest $request, string $id)
    {
        $subsidiary = Subsidiary::findOrFail($id);
        $subsidiary->update($request->validated());

        return new SubsidiaryResource($subsidiary);
    }

    #[OA\Delete(
        path: '/api/v1/subsidiaries/{id}',
        summary: 'Supprimer une filiale',
        security: [['bearerAuth' => []]],
        tags: ['Subsidiaries'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Filiale supprimée avec succès'
            ),
            new OA\Response(response: 404, description: 'Filiale non trouvée')
        ]
    )]
    public function destroy(string $id)
    {
        $subsidiary = Subsidiary::findOrFail($id);
        $subsidiary->delete();

        return response()->json(null, 204);
    }
}
