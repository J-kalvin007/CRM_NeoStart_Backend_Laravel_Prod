<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use OpenApi\Attributes as OA;

class TeamController extends Controller
{
    #[OA\Get(
        path: '/api/v1/teams',
        summary: 'Liste des équipes',
        tags: ['Teams'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Succès')
        ]
    )]
    public function index()
    {
        return TeamResource::collection(Team::with(['manager', 'sector'])->withCount('members')->get());
    }

    #[OA\Post(
        path: '/api/v1/teams',
        summary: 'Créer une nouvelle équipe',
        tags: ['Teams'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['sector_id', 'name'],
                properties: [
                    new OA\Property(property: 'sector_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'manager_id', type: 'string', format: 'uuid', nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Équipe créée avec succès'),
            new OA\Response(response: 422, description: 'Données invalides')
        ]
    )]
    public function store(StoreTeamRequest $request)
    {
        $team = Team::create($request->validated());
        return new TeamResource($team->load(['manager', 'sector']));
    }

    #[OA\Get(
        path: '/api/v1/teams/{id}',
        summary: 'Afficher une équipe spécifique',
        tags: ['Teams'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Succès'),
            new OA\Response(response: 404, description: 'Non trouvé')
        ]
    )]
    public function show(Team $team)
    {
        return new TeamResource($team->load(['manager', 'sector']));
    }

    #[OA\Put(
        path: '/api/v1/teams/{id}',
        summary: 'Mettre à jour une équipe',
        tags: ['Teams'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'sector_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'manager_id', type: 'string', format: 'uuid', nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Équipe mise à jour'),
            new OA\Response(response: 404, description: 'Non trouvé'),
            new OA\Response(response: 422, description: 'Données invalides')
        ]
    )]
    public function update(UpdateTeamRequest $request, Team $team)
    {
        $team->update($request->validated());
        return new TeamResource($team->load(['manager', 'sector']));
    }

    #[OA\Delete(
        path: '/api/v1/teams/{id}',
        summary: 'Supprimer une équipe',
        tags: ['Teams'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Équipe supprimée'),
            new OA\Response(response: 404, description: 'Non trouvé')
        ]
    )]
    public function destroy(Team $team)
    {
        $team->delete();
        return response()->noContent();
    }
}
