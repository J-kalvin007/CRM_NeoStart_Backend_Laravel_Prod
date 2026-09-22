<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use App\Http\Resources\PermissionResource;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Permissions', description: 'Consultation des permissions disponibles (lecture seule — gérées par le système)')]
class PermissionController extends Controller
{
    #[OA\Get(
        path: '/api/v1/permissions',
        summary: 'Lister toutes les permissions',
        description: 'Retourne la liste complète des permissions disponibles dans le système, triées par module puis par nom. Ces permissions sont fixes (définies par le seeder) et servent à être assignées aux rôles via POST /v1/roles ou PUT /v1/roles/{id}.',
        security: [['bearerAuth' => []]],
        tags: ['Permissions'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des permissions groupables par module',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id',         type: 'string', format: 'uuid'),
                            new OA\Property(property: 'name',       type: 'string', example: 'expenses.approve'),
                            new OA\Property(property: 'module',     type: 'string', example: 'Notes de Frais'),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function index()
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get();
        return PermissionResource::collection($permissions);
    }
}
