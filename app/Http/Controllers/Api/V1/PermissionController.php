<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Resources\PermissionResource;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Permissions', description: 'Gestion des permissions et assignation aux rôles')]
class PermissionController extends Controller
{
    #[OA\Get(
        path: '/api/v1/permissions',
        summary: 'Lister toutes les permissions',
        description: 'Retourne la liste complète des permissions disponibles dans le système, triées par module puis par nom.',
        security: [['bearerAuth' => []]],
        tags: ['Permissions'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des permissions groupées par module',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'name', type: 'string', example: 'expenses.approve'),
                                new OA\Property(property: 'label', type: 'string', example: 'Approuver dépense'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'module', type: 'string', example: 'Dépenses'),
                            ]
                        )),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function index()
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get();
        return response()->json([
            'data' => $permissions->map(function ($perm) {
                return [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'label' => $perm->label,
                    'description' => $perm->description,
                    'module' => $perm->module,
                ];
            })
        ]);
    }

    #[OA\Get(
        path: '/api/v1/permissions/grouped',
        summary: 'Lister les permissions groupées par module',
        description: 'Retourne les permissions organisées par module pour affichage dans l\'interface.',
        security: [['bearerAuth' => []]],
        tags: ['Permissions'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Permissions groupées par module',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'Administration.Utilisateurs', type: 'array', items: new OA\Items()),
                        new OA\Property(property: 'Réseau Médical.Professionnels', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
        ]
    )]
    public function grouped()
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get();
        $grouped = $permissions->groupBy('module');

        return response()->json([
            'data' => $grouped->map(function ($perms, $module) {
                return [
                    'module' => $module,
                    'permissions' => $perms->map(function ($perm) {
                        return [
                            'id' => $perm->id,
                            'name' => $perm->name,
                            'label' => $perm->label,
                            'description' => $perm->description,
                        ];
                    })->values()
                ];
            })->values()
        ]);
    }

    #[OA\Post(
        path: '/api/v1/roles/{roleId}/permissions',
        summary: 'Assigner des permissions à un rôle',
        description: 'Assigne une liste de permissions à un rôle spécifique.',
        security: [['bearerAuth' => []]],
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(
                name: 'roleId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permissions assignées avec succès'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Rôle non trouvé'),
        ]
    )]
    public function assignToRole(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);
        $permissions = $request->input('permissions', []);

        $role->syncPermissions($permissions);

        return response()->json([
            'message' => 'Permissions assignées avec succès',
            'role' => $role->name,
            'permissions_count' => count($permissions)
        ]);
    }

    #[OA\Get(
        path: '/api/v1/roles/{roleId}/permissions',
        summary: 'Lister les permissions d\'un rôle',
        description: 'Retourne la liste des permissions assignées à un rôle spécifique.',
        security: [['bearerAuth' => []]],
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(
                name: 'roleId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des permissions du rôle'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Rôle non trouvé'),
        ]
    )]
    public function getRolePermissions($roleId)
    {
        $role = Role::findOrFail($roleId);
        $permissions = $role->permissions()->orderBy('module')->orderBy('name')->get();

        return response()->json([
            'role' => $role->name,
            'data' => $permissions->map(function ($perm) {
                return [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'label' => $perm->label,
                    'module' => $perm->module,
                ];
            })
        ]);
    }
}
