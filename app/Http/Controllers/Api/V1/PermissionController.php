<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use App\Http\Resources\PermissionResource;

class PermissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/permissions",
     *     summary="Lister les permissions",
     *     tags={"Roles"},
     *     @OA\Response(response=200, description="Liste des permissions disponibles")
     * )
     * Liste toutes les permissions disponibles dans le système.
     */
    public function index()
    {
        $permissions = Permission::all();
        return PermissionResource::collection($permissions);
    }
}
