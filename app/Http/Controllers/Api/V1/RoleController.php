<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;

/**
 * @OA\Tag(
 *     name="Roles",
 *     description="Gestion des rôles (RBAC)"
 * )
 * @group Administration & Sécurité (RBAC)
 * 
 * Gestion des Rôles. Permet à un administrateur de définir les rôles de l'application
 * (ex: Directeur, Délégué, Manager) et de leur attribuer des permissions.
 */
class RoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/roles",
     *     summary="Lister les rôles",
     *     tags={"Roles"},
     *     @OA\Response(response=200, description="Liste des rôles avec leurs permissions")
     * )
     * Lister les Rôles
     * 
     * Retourne tous les rôles configurés dans le système avec leurs permissions associées.
     * Très utile pour alimenter un tableau d'administration sur le Frontend.
     * 
     * @response 200 [
     *   {
     *     "id": "uuid",
     *     "name": "medical-delegate",
     *     "permissions": [{"id": "...", "name": "create-expense"}]
     *   }
     * ]
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return RoleResource::collection($roles);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/roles",
     *     summary="Créer un rôle",
     *     tags={"Roles"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Rôle créé")
     * )
     * Crée un nouveau rôle et lui assigne des permissions optionnelles.
     */
    public function store(StoreRoleRequest $request)
    {
        $data = $request->validated();

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json(new RoleResource($role->load('permissions')), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/roles/{id}",
     *     summary="Afficher un rôle",
     *     tags={"Roles"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Détails du rôle")
     * )
     * Affiche un rôle spécifique.
     */
    public function show(Role $role)
    {
        return response()->json(new RoleResource($role->load('permissions')));
    }

    /**
     * @OA\Put(
     *     path="/api/v1/roles/{id}",
     *     summary="Modifier un rôle",
     *     tags={"Roles"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Rôle modifié")
     * )
     * Met à jour un rôle (nom et permissions).
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        $data = $request->validated();

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json(new RoleResource($role->load('permissions')));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/roles/{id}",
     *     summary="Supprimer un rôle",
     *     tags={"Roles"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Rôle supprimé")
     * )
     * Supprime un rôle.
     */
    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(null, 204);
    }
}
