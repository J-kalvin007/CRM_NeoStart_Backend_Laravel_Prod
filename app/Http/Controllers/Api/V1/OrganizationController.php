<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrganizationalUnit;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

class OrganizationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/v1/organization/tree",
     *     summary="Retrieve the full organizational hierarchy tree",
     *     description="Returns the nested tree structure of organizational units, typically starting from countries down to teams.",
     *     tags={"Organization"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(
     *                     property="children",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function tree(): JsonResponse
    {
        // On charge toutes les unités avec leurs enfants via la relation Eager Loading
        // Pour un arbre complet, on charge récursivement les enfants
        $tree = OrganizationalUnit::whereNull('parent_id')
            ->with('children.children.children.children.children.children') // Profondeur maximale (ex: country->sub->region->territory->zone->sector->team)
            ->get();

        return response()->json($tree);
    }

    /**
     * @OA\Get(
     *     path="/v1/organization/flat",
     *     summary="Retrieve flat list of organizational units",
     *     description="Useful for simple dropdowns that don't need tree parsing.",
     *     tags={"Organization"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function flat(): JsonResponse
    {
        $units = OrganizationalUnit::all();
        return response()->json($units);
    }
}
